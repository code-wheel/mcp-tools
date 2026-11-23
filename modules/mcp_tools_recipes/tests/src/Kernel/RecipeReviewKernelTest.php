<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_recipes\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools_recipes\Service\RecipesService;
use Drupal\Tests\mcp_tools\Traits\ForeignToolTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;

/**
 * A recipe that was written through MCP leaves a trail.
 *
 * With the admin scope a session can write a recipe with any content and
 * apply it in the next call. A recipe changes any config, grants any
 * permission and installs any module, past every rule of the other tools.
 * The admin scope is the gate for that. What the site keeps is a trail: the
 * recipe carries a mark until a person has read it, and the audit log says
 * when a marked recipe was applied.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_recipes')]
final class RecipeReviewKernelTest extends KernelTestBase {

  use ForeignToolTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_recipes'];

  /**
   * A folder that stands in for the site's root, with its recipes folder.
   */
  private string $recipeRoot;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['mcp_tools', 'user']);
    $this->createUser();
    $this->setUpCurrentUser([], ['mcp_tools use recipes']);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write', 'admin'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write', 'admin']);
    $this->recipeRoot = sys_get_temp_dir() . '/mcp_tools_recipes_' . bin2hex(random_bytes(8));
    mkdir($this->recipeRoot . '/recipes', 0755, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (isset($this->recipeRoot) && is_dir($this->recipeRoot)) {
      $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->recipeRoot, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
      foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
      }
      rmdir($this->recipeRoot);
    }
    parent::tearDown();
  }

  /**
   * The service as the site makes it, with the stand-in root.
   */
  private function service(): RecipesService {
    return new RecipesService(
      $this->container->get('config.factory'),
      $this->container->get('extension.list.module'),
      $this->container->get('file_system'),
      $this->recipeRoot,
      $this->container->get('mcp_tools.access_manager'),
      $this->container->get('mcp_tools.audit_logger'),
      $this->container->get('state'),
      $this->container->get('current_user'),
      $this->container->get('logger.channel.mcp_tools_recipes'),
    );
  }

  /**
   * The audit line of the last time a recipe was applied.
   */
  private function lastApplyLine(): string {
    $lines = array_filter($this->auditLines(), static fn(string $line): bool => str_contains($line, 'apply_recipe on recipe'));
    return (string) end($lines);
  }

  /**
   * How loud the audit lines of the last apply were: its start and its end.
   *
   * @return int[]
   *   The severities, the start line first.
   */
  private function lastApplySeverities(): array {
    $severities = [];
    $rows = $this->container->get('database')->select('watchdog', 'w')
      ->fields('w', ['message', 'variables', 'severity'])
      ->condition('type', 'mcp_tools')
      ->orderBy('wid', 'DESC')
      ->execute();
    foreach ($rows as $row) {
      $operation = ((array) unserialize($row->variables, ['allowed_classes' => FALSE]))['@operation'] ?? '';
      if (in_array($operation, ['apply_recipe', 'apply_recipe_start'], TRUE)) {
        array_unshift($severities, (int) $row->severity);
        if ($operation === 'apply_recipe_start') {
          break;
        }
      }
    }
    return $severities;
  }

  /**
   * What the status report says about this module.
   *
   * Asked the way this version of Drupal asks: the hook class from 11.2,
   * the function in the .install file before.
   */
  private function statusReport(): array {
    $handler = $this->container->get('module_handler');
    if (version_compare(\Drupal::VERSION, '11.2', '>=')) {
      return $handler->invoke('mcp_tools_recipes', 'runtime_requirements') ?? [];
    }
    $handler->loadInclude('mcp_tools_recipes', 'install');
    return $handler->invoke('mcp_tools_recipes', 'requirements', ['runtime']) ?? [];
  }

  /**
   * Written through MCP: marked, and the log says so when it is applied.
   */
  public function testARecipeWrittenThroughMcpLeavesATrail(): void {
    $grant = ['actions' => ['user.role.authenticated' => ['grantPermission' => 'administer permissions']]];
    $has = static fn(): bool => Role::load('authenticated')->hasPermission('administer permissions');
    $access = $this->container->get('mcp_tools.access_manager');
    $service = $this->service();
    $note = $this->recipeRoot . '/recipes/takeover/' . RecipesService::UNREVIEWED_FILE;

    $created = $service->createRecipe('takeover', 'Gives every user a permission.', ['config' => $grant]);
    $this->assertTrue($created['success'], json_encode($created));
    $this->assertFileExists($note);
    $this->assertStringContainsString(RecipesService::UNREVIEWED_FILE, $created['data']['message']);
    $this->assertStringContainsString('audit log', $created['data']['message']);

    // The admin scope is the gate: without it the recipe is not run.
    $access->setScopes(['read', 'write']);
    $applied = $service->applyRecipe('takeover');
    $this->assertFalse($applied['success']);
    $this->assertSame('INSUFFICIENT_SCOPE', $applied['code'] ?? NULL);
    $this->assertFalse($has());

    // With it the recipe is run. The answer and the audit log say that no
    // person had read it, and the mark stays where it is.
    $access->setScopes(['read', 'write', 'admin']);
    $applied = $service->applyRecipe('takeover');
    $this->assertTrue($applied['success'], json_encode($applied));
    $this->assertTrue($has());
    $this->assertStringContainsString('no person had reviewed it', $applied['data']['message']);
    $this->assertStringContainsString('"unreviewed":["takeover"]', $this->lastApplyLine());
    $this->assertFileExists($note, 'Applying the recipe took its mark away.');
    // Nobody said yes to it, so both lines stand out as warnings, and the
    // status report names the recipe.
    $this->assertSame([RfcLogLevel::WARNING, RfcLogLevel::WARNING], $this->lastApplySeverities());
    $report = $this->statusReport();
    $this->assertCount(1, $report);
    $this->assertStringContainsString('takeover', (string) reset($report)['description']);

    // A person has read it and taken the note out of its folder.
    unlink($note);
    Role::load('authenticated')->revokePermission('administer permissions')->save();
    $applied = $service->applyRecipe('takeover');
    $this->assertTrue($applied['success'], json_encode($applied));
    $this->assertTrue($has());
    $this->assertStringNotContainsString('reviewed', $applied['data']['message']);
    $this->assertStringNotContainsString('unreviewed', $this->lastApplyLine());
    $this->assertSame([RfcLogLevel::NOTICE, RfcLogLevel::NOTICE], $this->lastApplySeverities());
    $this->assertSame([], $this->statusReport());

    // A recipe a person put on the server never had the note.
    Role::load('authenticated')->revokePermission('administer permissions')->save();
    mkdir($this->recipeRoot . '/recipes/by_hand');
    file_put_contents($this->recipeRoot . '/recipes/by_hand/recipe.yml', Yaml::encode(['name' => 'By hand', 'type' => 'Site', 'config' => $grant]));
    $applied = $service->applyRecipe('by_hand');
    $this->assertTrue($applied['success'], json_encode($applied));
    $this->assertTrue($has());
    $this->assertStringContainsString('apply_recipe on recipe "by_hand"', $this->lastApplyLine());
    $this->assertStringNotContainsString('unreviewed', $this->lastApplyLine());
  }

  /**
   * A recipe that switches the audit log off is in the log all the same.
   *
   * A recipe changes any config, the settings of MCP Tools among it. The
   * line for an apply was written after the recipe had run, so a recipe
   * that set access.audit_logging to false left no line of its own run.
   */
  public function testARecipeCannotTakeItselfOutOfTheLog(): void {
    $service = $this->service();
    $quiet = ['actions' => ['mcp_tools.settings' => ['simpleConfigUpdate' => ['access.audit_logging' => FALSE]]]];
    $created = $service->createRecipe('quiet', 'Switches the audit log off.', ['config' => $quiet]);
    $this->assertTrue($created['success'], json_encode($created));

    $applied = $service->applyRecipe('quiet');
    $this->assertTrue($applied['success'], json_encode($applied));
    $this->assertFalse((bool) $this->config('mcp_tools.settings')->get('access.audit_logging'));

    // The line that names the recipe was written before the recipe ran.
    $started = array_filter($this->auditLines(), static fn(string $line): bool => str_contains($line, 'apply_recipe_start on recipe "quiet"'));
    $this->assertCount(1, $started, 'A recipe that switched the audit log off left no line of its own run.');
    $this->assertStringContainsString('"unreviewed":["quiet"]', (string) end($started));

    // The answer and the site's log say what the recipe did to the log.
    $this->assertStringContainsString('switched the audit log of MCP Tools off', $applied['data']['message']);
    $warnings = $this->container->get('database')->select('watchdog', 'w')
      ->fields('w', ['message'])
      ->condition('type', 'mcp_tools_recipes')
      ->condition('severity', RfcLogLevel::WARNING)
      ->execute()
      ->fetchCol();
    $this->assertCount(1, $warnings);
    $this->assertStringContainsString('switched the audit log of MCP Tools off', $warnings[0]);

    // A recipe that leaves the log alone has its line and no such warning.
    $this->config('mcp_tools.settings')->set('access.audit_logging', TRUE)->save();
    mkdir($this->recipeRoot . '/recipes/plain');
    file_put_contents($this->recipeRoot . '/recipes/plain/recipe.yml', Yaml::encode(['name' => 'Plain', 'type' => 'Site']));
    $applied = $service->applyRecipe('plain');
    $this->assertTrue($applied['success'], json_encode($applied));
    $this->assertStringNotContainsString('audit log', $applied['data']['message']);
    $started = array_filter($this->auditLines(), static fn(string $line): bool => str_contains($line, 'apply_recipe_start on recipe "plain"'));
    $this->assertCount(1, $started);
    $this->assertStringNotContainsString('unreviewed', (string) end($started));
  }

  /**
   * A recipe's inputs are collected: given, or their default.
   *
   * The tool never collected them, and ${name} was stored as written.
   */
  public function testInputsAreCollected(): void {
    $this->installConfig(['system']);
    mkdir($this->recipeRoot . '/recipes/named');
    file_put_contents($this->recipeRoot . '/recipes/named/recipe.yml', Yaml::encode([
      'name' => 'Named',
      'type' => 'Site',
      'input' => [
        'site_name' => [
          'data_type' => 'string',
          'description' => 'The name of the site.',
          'default' => ['source' => 'value', 'value' => 'Default name'],
        ],
      ],
      'config' => ['actions' => ['system.site' => ['simpleConfigUpdate' => ['name' => '${site_name}']]]],
    ]));
    $service = $this->service();

    $applied = $service->applyRecipe('named', ['site_name' => 'Given name']);
    $this->assertTrue($applied['success'], json_encode($applied));
    $this->assertSame('Given name', $this->config('system.site')->get('name'), 'The input was not used: the placeholder was stored as written.');

    $this->assertFalse($service->applyRecipe('named', ['site_nmae' => 'Typo'])['success'], 'An input the recipe does not have was taken.');
  }

  /**
   * A core recipe that installs a theme is valid.
   *
   * Every install entry was checked as a module: core's
   * core_recommended_front_end_theme failed with "Required module olivero
   * is not available", and apply refused it.
   */
  public function testACoreRecipeThatInstallsAThemeIsValid(): void {
    $result = $this->container->get('mcp_tools_recipes.recipes')->validateRecipe('core_recommended_front_end_theme');
    $this->assertTrue($result['success'], json_encode($result));
    $this->assertTrue($result['data']['valid'] ?? FALSE, 'A core recipe that installs a theme was not valid: ' . json_encode($result['data']['errors'] ?? []));
  }

}
