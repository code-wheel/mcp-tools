<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\mcp_tools_recipes\Service\RecipesService;
use Drupal\Tests\mcp_tools\Traits\SdkSuspensionTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Request\ElicitRequest;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

/**
 * The client's user is asked before the two strongest calls of our own run.
 *
 * With the admin scope a connection grants a permission Drupal marks for
 * trusted roles only, and applies a recipe that it wrote itself and that no
 * person has read. Both ran without a question, and the audit log said so
 * afterwards. Where the client can ask its user, the server now asks first,
 * as it does before a destructive tool of another module. A site can refuse
 * such a call for a client that cannot ask.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class AsksFirstKernelTest extends KernelTestBase {

  use SdkSuspensionTrait;
  use UserCreationTrait;

  private const RESTRICTED = 'administer account settings';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_structure',
    'mcp_tools_recipes',
  ];

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
    Role::create(['id' => 'builder', 'label' => 'Builder'])->save();
    // The first user is uid 1 and passes every permission check.
    $this->createUser();
    $this->setUpCurrentUser([], ['mcp_tools use structure', 'mcp_tools use recipes']);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write', 'admin'])
      ->set('access.read_only_mode', FALSE)
      ->set('access.config_only_mode', FALSE)
      ->save();

    // The recipes of this test go to a folder of their own, not to the
    // root of the site the test runs in.
    $this->recipeRoot = sys_get_temp_dir() . '/mcp_tools_asks_' . bin2hex(random_bytes(8));
    mkdir($this->recipeRoot . '/recipes', 0755, TRUE);
    $this->container->set('mcp_tools_recipes.recipes', new RecipesService(
      $this->container->get('config.factory'),
      $this->container->get('extension.list.module'),
      $this->container->get('file_system'),
      $this->recipeRoot,
      $this->container->get('mcp_tools.access_manager'),
      $this->container->get('mcp_tools.audit_logger'),
      $this->container->get('state'),
      $this->container->get('current_user'),
      $this->container->get('logger.channel.mcp_tools_recipes'),
    ));
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
   * Starts a call in a fiber, as the SDK does.
   *
   * @return array{0: \Fiber, 1: object|null}
   *   The fiber, and the request the server wants the client to answer, or
   *   NULL when the call finished without asking.
   */
  private function start(string $tool, array $arguments, bool $clientCanAsk, array $scopes = ['read', 'write', 'admin']): array {
    $this->container->get('mcp_tools.access_manager')->setScopes($scopes);
    $handler = new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
      NULL,
      $this->container->get('event_dispatcher'),
      NULL,
      $this->container->get('mcp_tools.access_manager'),
      $this->container->get('mcp_tools.tool_call_context'),
    );
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->willReturnCallback(
      static fn(string $key, mixed $default = NULL) => $key === 'client_capabilities'
        ? ($clientCanAsk ? ['elicitation' => []] : [])
        : $default,
    );
    $session->method('getId')->willReturn(Uuid::v4());
    $request = (new CallToolRequest($tool, $arguments))->withId(1);

    $fiber = new \Fiber(static fn() => $handler->handle($request, $session));
    $value = $fiber->start();
    while (!$fiber->isTerminated() && $this->suspendedRequest($value) === NULL) {
      $value = $fiber->resume();
    }
    return [$fiber, $fiber->isTerminated() ? NULL : $this->suspendedRequest($value)];
  }

  /**
   * Gives the client's answer and runs the call to its end.
   */
  private function answer(\Fiber $fiber, array $answer): Response {
    $fiber->resume(new Response(1, $answer));
    while (!$fiber->isTerminated()) {
      $fiber->resume();
    }
    return $fiber->getReturn();
  }

  /**
   * Whether the role has the permission, read from storage.
   */
  private function has(string $role, string $permission): bool {
    $this->container->get('entity_type.manager')->getStorage('user_role')->resetCache();
    return Role::load($role)->hasPermission($permission);
  }

  /**
   * The last audit line with one of these words, and how loud it was.
   *
   * @return array{0: string, 1: int}
   *   The line with its values filled in, and its severity.
   */
  private function lastAuditLine(string $operation): array {
    $rows = $this->container->get('database')->select('watchdog', 'w')
      ->fields('w', ['message', 'variables', 'severity'])
      ->condition('type', 'mcp_tools')
      ->orderBy('wid', 'DESC')
      ->execute();
    foreach ($rows as $row) {
      $line = strtr($row->message, (array) unserialize($row->variables, ['allowed_classes' => FALSE]));
      if (str_contains($line, $operation . ' on ')) {
        return [$line, (int) $row->severity];
      }
    }
    return ['', -1];
  }

  /**
   * A permission for trusted roles only: asked, and a yes grants it.
   */
  public function testARestrictedPermissionIsAskedAbout(): void {
    $call = ['role' => 'builder', 'permissions' => [self::RESTRICTED]];
    [$fiber, $asked] = $this->start('mcp_structure_grant_permissions', $call, TRUE);

    $this->assertInstanceOf(ElicitRequest::class, $asked, 'The server asks before it grants a permission for trusted roles only.');
    $this->assertStringContainsString(self::RESTRICTED, $asked->message);
    $this->assertStringContainsString('builder', $asked->message);
    $this->assertFalse($this->has('builder', self::RESTRICTED), 'Nothing was granted before the answer.');

    $response = $this->answer($fiber, ['action' => 'accept', 'content' => ['confirm' => TRUE]]);
    $this->assertTrue($response->result->structuredContent['success']);
    $this->assertTrue($this->has('builder', self::RESTRICTED));
    // A person has seen it: the line says so, and is a notice.
    [$line, $severity] = $this->lastAuditLine('grant_permissions');
    $this->assertStringContainsString('"confirmed_by_user":true', $line);
    $this->assertSame(RfcLogLevel::NOTICE, $severity);
  }

  /**
   * A no grants nothing.
   */
  public function testANoGrantsNothing(): void {
    [$fiber, $asked] = $this->start('mcp_structure_create_role', ['id' => 'helper', 'label' => 'Helper', 'permissions' => [self::RESTRICTED]], TRUE);
    $this->assertInstanceOf(ElicitRequest::class, $asked);
    $this->assertStringContainsString(self::RESTRICTED, $asked->message);

    $response = $this->answer($fiber, ['action' => 'accept', 'content' => ['confirm' => FALSE]]);
    $this->assertFalse($response->result->structuredContent['success']);
    $this->assertStringContainsString('Declined by the user', $response->result->structuredContent['message']);
    $this->assertNull(Role::load('helper'));
  }

  /**
   * A client that cannot ask: run and written as a warning, or refused.
   */
  public function testAClientThatCannotAsk(): void {
    $call = ['role' => 'builder', 'permissions' => [self::RESTRICTED]];

    // The site insists on a yes: refused.
    $this->config('mcp_tools.settings')->set('access.confirm_destructive_tools', TRUE)->save();
    [$fiber, $asked] = $this->start('mcp_structure_grant_permissions', $call, FALSE);
    $this->assertNull($asked);
    $result = $fiber->getReturn()->result;
    $this->assertTrue($result->isError);
    $this->assertSame('CONFIRMATION_REQUIRED', $result->structuredContent['error_code']);
    $this->assertStringContainsString(self::RESTRICTED, $result->structuredContent['message']);
    $this->assertFalse($this->has('builder', self::RESTRICTED));

    // The site does not: granted, and the line stands out as a warning.
    $this->config('mcp_tools.settings')->set('access.confirm_destructive_tools', FALSE)->save();
    [$fiber, $asked] = $this->start('mcp_structure_grant_permissions', $call, FALSE);
    $this->assertNull($asked);
    $this->assertTrue($fiber->getReturn()->result->structuredContent['success']);
    $this->assertTrue($this->has('builder', self::RESTRICTED));
    [$line, $severity] = $this->lastAuditLine('grant_permissions');
    $this->assertStringContainsString('"restrict_access":["' . self::RESTRICTED . '"]', $line);
    $this->assertStringNotContainsString('confirmed_by_user', $line);
    $this->assertSame(RfcLogLevel::WARNING, $severity);
  }

  /**
   * Nobody is asked about a call that needs no yes, or that is refused.
   */
  public function testNoQuestionWhereNoneIsNeeded(): void {
    // An everyday permission.
    [$fiber, $asked] = $this->start('mcp_structure_grant_permissions', ['role' => 'builder', 'permissions' => ['access user profiles']], TRUE);
    $this->assertNull($asked, 'The user was asked about a permission that is not for trusted roles only.');
    $this->assertTrue($fiber->getReturn()->result->structuredContent['success']);
    [, $severity] = $this->lastAuditLine('grant_permissions');
    $this->assertSame(RfcLogLevel::NOTICE, $severity);

    // The write scope: the grant is refused, so there is nothing to ask.
    [$fiber, $asked] = $this->start('mcp_structure_grant_permissions', ['role' => 'builder', 'permissions' => [self::RESTRICTED]], TRUE, ['read', 'write']);
    $this->assertNull($asked, 'The user was asked to confirm a grant that is then refused.');
    $this->assertFalse($fiber->getReturn()->result->structuredContent['success']);
    $this->assertFalse($this->has('builder', self::RESTRICTED));

    // One of MCP Tools' own permissions: refused with every scope.
    [$fiber, $asked] = $this->start('mcp_structure_grant_permissions', ['role' => 'builder', 'permissions' => ['mcp_tools use structure']], TRUE);
    $this->assertNull($asked);
    $this->assertFalse($fiber->getReturn()->result->structuredContent['success']);
  }

  /**
   * A destructive tool of MCP Tools itself is asked about, as it was.
   *
   * The handler's comment said our own tools are left out. They never
   * were. This holds what it does.
   */
  public function testOurOwnDestructiveToolIsAskedAbout(): void {
    [$fiber, $asked] = $this->start('mcp_structure_delete_role', ['id' => 'builder'], TRUE);
    $this->assertInstanceOf(ElicitRequest::class, $asked);
    $this->assertStringContainsString('mcp_structure_delete_role', $asked->message);
    $this->assertStringContainsString('destructive', $asked->message);
    $this->assertNotNull(Role::load('builder'));

    $response = $this->answer($fiber, ['action' => 'accept', 'content' => ['confirm' => TRUE]]);
    $this->assertTrue($response->result->structuredContent['success']);
    $this->container->get('entity_type.manager')->getStorage('user_role')->resetCache();
    $this->assertNull(Role::load('builder'));
  }

  /**
   * A recipe no person has read: asked. A recipe a person has read: not.
   */
  public function testAnUnreviewedRecipeIsAskedAbout(): void {
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write', 'admin']);
    $recipes = $this->container->get('mcp_tools_recipes.recipes');
    $created = $recipes->createRecipe('takeover', 'Gives every user a permission.', [
      'config' => ['actions' => ['user.role.authenticated' => ['grantPermission' => 'administer permissions']]],
    ]);
    $this->assertTrue($created['success'], json_encode($created));

    [$fiber, $asked] = $this->start('mcp_recipes_apply', ['recipe_name' => 'takeover'], TRUE);
    $this->assertInstanceOf(ElicitRequest::class, $asked, 'The server asks before it applies a recipe no person has read.');
    $this->assertStringContainsString('takeover', $asked->message);
    $this->assertFalse($this->has('authenticated', 'administer permissions'), 'The recipe ran before the answer.');

    // No: nothing is run.
    $response = $this->answer($fiber, ['action' => 'decline']);
    $this->assertFalse($response->result->structuredContent['success']);
    $this->assertFalse($this->has('authenticated', 'administer permissions'));

    // Yes: it runs, and the line says a person said yes.
    [$fiber, $asked] = $this->start('mcp_recipes_apply', ['recipe_name' => 'takeover'], TRUE);
    $this->assertInstanceOf(ElicitRequest::class, $asked);
    $response = $this->answer($fiber, ['action' => 'accept', 'content' => ['confirm' => TRUE]]);
    $this->assertTrue($response->result->structuredContent['success'], json_encode($response->result->structuredContent));
    $this->assertTrue($this->has('authenticated', 'administer permissions'));
    [$line, $severity] = $this->lastAuditLine('apply_recipe');
    $this->assertStringContainsString('"unreviewed":["takeover"]', $line);
    $this->assertStringContainsString('"confirmed_by_user":true', $line);
    $this->assertSame(RfcLogLevel::NOTICE, $severity);

    // A person has read it and taken the note out: no question.
    unlink($this->recipeRoot . '/recipes/takeover/' . RecipesService::UNREVIEWED_FILE);
    [$fiber, $asked] = $this->start('mcp_recipes_apply', ['recipe_name' => 'takeover'], TRUE);
    $this->assertNull($asked, 'The user was asked about a recipe a person has reviewed.');
    $this->assertTrue($fiber->getReturn()->result->structuredContent['success']);
  }

}
