<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_ai\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Verifies the Tool API -> drupal/ai FunctionCall bridge.
 *
 * @group mcp_tools_ai
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class AiFunctionCallBridgeTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'update',
    'ai',
    'tool',
    'mcp_tools',
    'mcp_tools_ai',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['mcp_tools_ai']);
    // The first account is uid 1, which passes every permission check. Keep
    // it out of the way so the tests below run as ordinary users.
    $this->createUser();
  }

  /**
   * The default (read/explain) curation exposes read tools and executes one.
   */
  public function testReadOnlyCurationAndExecution(): void {
    /** @var \Drupal\Component\Plugin\PluginManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.ai.function_calls');

    $bridged = array_filter(
      array_keys($manager->getDefinitions()),
      static fn (string $id): bool => str_starts_with($id, 'mcp_tools_tool:'),
    );
    $this->assertNotEmpty($bridged, 'Tool API tools are exposed as AI Function Calls.');

    // A known read-only tool must be present under the default curation.
    $site_status = 'mcp_tools_tool:mcp_tools_get_site_status';
    $this->assertContains($site_status, $bridged);

    // And it must execute through the AI manager, returning real output, for
    // a user who holds the tool's permission.
    $this->setUpCurrentUser([], ['mcp_tools use site_health']);
    /** @var \Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface $fc */
    $fc = $manager->createInstance($site_status);
    $fc->execute();
    $result = json_decode($fc->getReadableOutput(), TRUE);
    $this->assertTrue($result['success'], $fc->getReadableOutput());
    $this->assertArrayHasKey('drupal_version', $result['data']);
  }

  /**
   * A user without the tool's permission cannot run it through the bridge.
   *
   * Drupal AI does not check access before it executes a function call, and
   * Tool API's execute() does not either, so the bridge has to.
   */
  public function testExecutionEnforcesTheToolAccessCheck(): void {
    $manager = $this->container->get('plugin.manager.ai.function_calls');
    $site_status = 'mcp_tools_tool:mcp_tools_get_site_status';

    $account = $this->setUpCurrentUser();
    $this->assertNotSame(1, (int) $account->id());
    $this->assertFalse($account->hasPermission('mcp_tools use site_health'));

    $fc = $manager->createInstance($site_status);
    $fc->execute();
    $output = $fc->getReadableOutput();
    $result = json_decode($output, TRUE);
    $this->assertIsArray($result, $output);
    $this->assertFalse($result['success'], 'A user without the permission is refused.');
    $this->assertStringContainsString('Access denied', $result['message']);
    $this->assertStringNotContainsString('drupal_version', $output, 'No tool data leaks with the refusal.');

    // Anonymous is refused the same way.
    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    $fc = $manager->createInstance($site_status);
    $fc->execute();
    $this->assertStringNotContainsString('drupal_version', $fc->getReadableOutput());
    $this->assertFalse(json_decode($fc->getReadableOutput(), TRUE)['success']);
  }

  /**
   * Another module's write tool answers to scopes and read-only mode.
   *
   * The bridge exposes every Tool API tool, not only MCP Tools' own. Those
   * check scopes and read-only mode in their access check; a tool of
   * another provider does not, so the bridge applies the same rule to it.
   */
  public function testForeignWriteToolAnswersToScopesAndReadOnlyMode(): void {
    $this->enableModules(['foreign_tool_test']);
    $this->installConfig(['mcp_tools']);
    $this->config('mcp_tools_ai.settings')->set('exposed_operations', ['read', 'explain', 'write'])->save();
    $this->container->get('plugin.manager.ai.function_calls')->clearCachedDefinitions();
    $this->setUpCurrentUser();
    $state = $this->container->get('state');
    $write = 'mcp_tools_tool:foreign_test_write';

    $run = function () use ($write): array {
      $fc = $this->container->get('plugin.manager.ai.function_calls')->createInstance($write);
      $fc->execute();
      return (array) json_decode($fc->getReadableOutput(), TRUE);
    };

    // The default scopes are read only.
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.default_scopes', ['read'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $result = $run();
    $this->assertFalse($result['success'] ?? TRUE, 'Without the write scope the tool is refused.');
    $this->assertFalse((bool) $state->get('foreign_tool_test.wrote', FALSE));

    // With the write scope it runs.
    $this->config('mcp_tools.settings')->set('access.default_scopes', ['read', 'write'])->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $result = $run();
    $this->assertTrue($result['success'] ?? FALSE, json_encode($result));
    $state->delete('foreign_tool_test.wrote');

    // Read-only mode stops it again.
    $this->config('mcp_tools.settings')->set('access.read_only_mode', TRUE)->save();
    $result = $run();
    $this->assertFalse($result['success'] ?? TRUE, 'Read-only mode stops the tool.');
    $this->assertFalse((bool) $state->get('foreign_tool_test.wrote', FALSE));
  }

  /**
   * The operation-based curation filter actually filters.
   *
   * Core MCP Tools provides only read-operation tools, so the default
   * (read + explain) exposes them and restricting to write hides them all —
   * proving exposure follows the configured operation set.
   */
  public function testCurationFilterIsApplied(): void {
    $manager = $this->container->get('plugin.manager.ai.function_calls');
    $count = static fn (): int => count(array_filter(
      array_keys($manager->getDefinitions()),
      static fn (string $id): bool => str_starts_with($id, 'mcp_tools_tool:'),
    ));

    // Default read + explain curation exposes the core read tools.
    $this->assertGreaterThan(0, $count());

    // Restricting exposure to write hides every read-only tool (core has no
    // write tools), so nothing is bridged.
    $this->config('mcp_tools_ai.settings')
      ->set('exposed_operations', ['write'])
      ->save();
    $manager->clearCachedDefinitions();
    $this->assertSame(0, $count(), 'Restricting to write hides all read-only core tools.');

    // Re-enabling read brings them back.
    $this->config('mcp_tools_ai.settings')
      ->set('exposed_operations', ['read'])
      ->save();
    $manager->clearCachedDefinitions();
    $this->assertGreaterThan(0, $count());
  }

}
