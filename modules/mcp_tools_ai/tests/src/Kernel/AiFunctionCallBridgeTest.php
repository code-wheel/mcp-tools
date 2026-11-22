<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_ai\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\mcp_tools\Traits\ForeignToolTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Verifies the Tool API -> drupal/ai FunctionCall bridge.
 *
 * @group mcp_tools_ai
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class AiFunctionCallBridgeTest extends KernelTestBase {

  use ForeignToolTestTrait;
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
   * Another module's write tool is limited and audited through the bridge.
   *
   * The MCP server counts such a tool against the write limits and writes
   * its audit line. An AI agent that calls it through this bridge is held
   * to the same.
   */
  public function testForeignWriteToolIsLimitedAndAudited(): void {
    $this->enableModules(['foreign_tool_test', 'dblog']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['mcp_tools']);
    $this->config('mcp_tools_ai.settings')->set('exposed_operations', ['read', 'explain', 'write'])->save();
    $this->container->get('plugin.manager.ai.function_calls')->clearCachedDefinitions();
    $this->setUpCurrentUser();
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.default_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_writes_per_minute', 1)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);

    $run = function (): array {
      $fc = $this->container->get('plugin.manager.ai.function_calls')->createInstance('mcp_tools_tool:foreign_test_write');
      $fc->execute();
      return (array) json_decode($fc->getReadableOutput(), TRUE);
    };

    $first = $run();
    $this->assertTrue($first['success'] ?? FALSE, json_encode($first));

    $second = $run();
    $this->assertFalse($second['success'] ?? TRUE, 'The call over the limit is refused: ' . json_encode($second));
    $this->assertStringContainsStringIgnoringCase('rate limit', (string) ($second['message'] ?? ''));
    $this->assertGreaterThan(0, (int) ($second['retry_after'] ?? 0));

    $lines = $this->auditLines();
    $this->assertCount(1, $lines, implode("\n", $lines));
    $this->assertStringContainsString('run_write_tool on tool "foreign_test_write"', $lines[0]);
  }

  /**
   * A model's tool call finds our function call.
   *
   * A model answers with a function name. Drupal AI looks the name up in
   * the definitions and makes the plugin by the "id" it finds there. Our
   * deriver left the base ID in every definition, so each call of a real
   * model ended in "The mcp_tools_tool plugin does not exist". The tests
   * made the function calls by their full ID and never took that path. It
   * showed with the first real model: AI Agents and a local model.
   */
  public function testAModelsToolCallFindsOurFunctionCall(): void {
    $manager = $this->container->get('plugin.manager.ai.function_calls');
    $this->setUpCurrentUser([], ['mcp_tools use site_health']);

    $definition = $manager->getDefinition('mcp_tools_tool:mcp_tools_get_site_status');
    $this->assertSame('mcp_tools_tool:mcp_tools_get_site_status', $definition['id']);

    // The way an agent gets there: from the name the model used.
    $fc = $manager->getFunctionCallFromFunctionName($definition['function_name']);
    $this->assertSame('mcp_tools_tool:mcp_tools_get_site_status', $fc->getPluginId());
    $fc->execute();
    $result = json_decode($fc->getReadableOutput(), TRUE);
    $this->assertTrue($result['success'] ?? FALSE, $fc->getReadableOutput());
    $this->assertArrayHasKey('drupal_version', $result['data']);

    // And for every one of them: the ID in a definition is its own key.
    foreach ($manager->getDefinitions() as $id => $each) {
      if (str_starts_with($id, 'mcp_tools_tool:')) {
        $this->assertSame($id, $each['id']);
      }
    }
  }

  /**
   * An argument the model got wrong is answered, and the agent goes on.
   *
   * Drupal AI reads a model's arguments into the function call before it
   * runs it, and that step throws for a value it cannot read: a word where
   * an object belongs, or JSON with a brace too many. Nothing caught it, so
   * the whole agent run ended there (two tasks of a small model on
   * 2026-11-21). The model has to be told, as for any other failed call.
   */
  public function testAnArgumentThatCannotBeReadIsAnsweredNotThrown(): void {
    $this->enableModules(['node', 'field', 'text', 'filter', 'mcp_tools_content']);
    $this->installConfig(['mcp_tools']);
    $this->config('mcp_tools_ai.settings')->set('exposed_operations', ['read', 'explain', 'write'])->save();
    $manager = $this->container->get('plugin.manager.ai.function_calls');
    $manager->clearCachedDefinitions();
    $this->setUpCurrentUser();

    $definition = $manager->getDefinition('mcp_tools_tool:mcp_create_content');
    $input = $manager->getFunctionCallFromFunctionName($definition['function_name'])->normalize();

    $cases = [
      'a word where an object belongs' => 'the body',
      'JSON with a brace too many' => '{"body": {"value": "Hello"}}}',
    ];
    foreach ($cases as $case => $fields) {
      // The way an agent gets there: from the model's call to our plugin.
      $call = new ToolsFunctionOutput($input, 'call_1', ['type' => 'news', 'title' => 'Hello', 'fields' => $fields]);
      $fc = $manager->convertToolResponseToObject($call);
      $fc->execute();
      $result = json_decode($fc->getReadableOutput(), TRUE);
      $this->assertIsArray($result, $case . ': ' . $fc->getReadableOutput());
      $this->assertFalse($result['success'], $case);
      $this->assertStringContainsString('"fields"', $result['message'], $case);
      $this->assertStringContainsString('Nothing was run', $result['message'], $case);
    }

    // A call with arguments that can be read is not held up by this.
    $call = new ToolsFunctionOutput($input, 'call_2', ['type' => 'news', 'title' => 'Hello', 'fields' => ['body' => 'Hello']]);
    $fc = $manager->convertToolResponseToObject($call);
    $fc->execute();
    $this->assertStringNotContainsString('Nothing was run', $fc->getReadableOutput());
  }

  /**
   * A tool is told that the AI bridge called it, and gets Tool API's checks.
   *
   * The bridge ran tools by hand: made, inputs set, executed. A tool run
   * that way is not told who called it. It goes through Tool API's runner
   * now, like the MCP server's calls.
   */
  public function testToolLearnsWhoCalledIt(): void {
    $this->enableModules(['foreign_tool_test']);
    $this->installConfig(['mcp_tools']);
    $this->container->get('plugin.manager.ai.function_calls')->clearCachedDefinitions();
    $this->setUpCurrentUser();

    $fc = $this->container->get('plugin.manager.ai.function_calls')->createInstance('mcp_tools_tool:foreign_test_whoami');
    $fc->execute();
    $result = (array) json_decode($fc->getReadableOutput(), TRUE);

    $this->assertTrue($result['success'] ?? FALSE, $fc->getReadableOutput());
    $this->assertSame('mcp_tools_ai', $result['data']['caller'] ?? NULL);
  }

  /**
   * A call the tool itself refuses is not counted against the write limit.
   */
  public function testRefusedForeignCallIsNotCounted(): void {
    $this->enableModules(['foreign_tool_test', 'dblog']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['mcp_tools']);
    $this->config('mcp_tools_ai.settings')->set('exposed_operations', ['read', 'explain', 'write'])->save();
    $this->container->get('plugin.manager.ai.function_calls')->clearCachedDefinitions();
    $this->setUpCurrentUser();
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.default_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_writes_per_minute', 1)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);

    $run = function (string $tool): array {
      $fc = $this->container->get('plugin.manager.ai.function_calls')->createInstance('mcp_tools_tool:' . $tool);
      $fc->execute();
      return (array) json_decode($fc->getReadableOutput(), TRUE);
    };

    foreach ([1, 2, 3] as $n) {
      $refused = $run('foreign_test_forbidden');
      $this->assertFalse($refused['success'] ?? TRUE, "refused $n");
      $this->assertStringContainsString('Access denied', (string) ($refused['message'] ?? ''));
    }
    $this->assertFalse((bool) $this->container->get('state')->get('foreign_tool_test.forbidden_ran', FALSE));
    $this->assertTrue($run('foreign_test_write')['success'] ?? FALSE, 'The one write this minute allows still runs.');
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
