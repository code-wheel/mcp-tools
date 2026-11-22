<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\mcp_tools\Traits\ForeignToolTestTrait;
use Drupal\mcp_tools\Policy\ToolRefusal;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tool\Tool\Invoker;
use Drupal\tool\Tool\ToolInvocation;

/**
 * The site's rules for a tool of any module, without the MCP server.
 *
 * Nothing in this test is MCP: a tool definition, values and an invoker go
 * in, Tool API's invocation or a refusal comes out. It is what the MCP
 * server and the Drupal AI bridge both run tools through, and what can hang
 * on Tool API's runner once that has a place to refuse a call.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ToolPolicyKernelTest extends KernelTestBase {

  use ForeignToolTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_cache',
    'foreign_tool_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['mcp_tools']);
    $this->setUpCurrentUser();
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write', 'admin'])
      ->set('access.read_only_mode', FALSE)
      ->set('access.config_only_mode', FALSE)
      // The tests here are about scopes, limits and the audit line, on a
      // site that does not insist on a yes. The last test is about one
      // that does, which is what a new site is.
      ->set('access.confirm_destructive_tools', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write', 'admin']);
  }

  /**
   * Runs a tool the way any caller would.
   */
  private function runTool(string $tool, bool $enforce = TRUE): ToolInvocation|ToolRefusal {
    $definition = $this->container->get('plugin.manager.tool')->getDefinition($tool);
    return $this->container->get('mcp_tools.tool_policy')->run($definition, [], new Invoker('some_caller'), $enforce);
  }

  /**
   * A tool that is let through runs, and is told who called it.
   */
  public function testAnAdmittedCallRunsThroughToolApi(): void {
    $outcome = $this->runTool('foreign_test_whoami');

    $this->assertInstanceOf(ToolInvocation::class, $outcome);
    $this->assertTrue($outcome->hasExecuted());
    $this->assertTrue($outcome->getResult()->isSuccess());
    $this->assertSame(['caller' => 'some_caller'], $outcome->getResult()->getContextValues());
  }

  /**
   * Read-only mode and a missing scope refuse a write before it runs.
   */
  public function testModesAndScopesRefuse(): void {
    $this->config('mcp_tools.settings')->set('access.read_only_mode', TRUE)->save();
    $outcome = $this->runTool('foreign_test_write');
    $this->assertInstanceOf(ToolRefusal::class, $outcome);
    $this->assertSame(ToolRefusal::ACCESS, $outcome->reason);
    $this->assertFalse($this->wroteSinceLastLook());
    $this->assertInstanceOf(ToolInvocation::class, $this->runTool('foreign_test_read'), 'Reading goes on.');

    $this->config('mcp_tools.settings')->set('access.read_only_mode', FALSE)->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read']);
    $outcome = $this->runTool('foreign_test_write');
    $this->assertInstanceOf(ToolRefusal::class, $outcome);
    $this->assertSame(ToolRefusal::ACCESS, $outcome->reason);
    $this->assertFalse($this->wroteSinceLastLook());
  }

  /**
   * A write over the limit is refused, with the seconds to wait.
   */
  public function testTheWriteLimit(): void {
    $this->config('mcp_tools.settings')
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_writes_per_minute', 2)
      ->save();

    $this->assertInstanceOf(ToolInvocation::class, $this->runTool('foreign_test_write'));
    $this->assertInstanceOf(ToolInvocation::class, $this->runTool('foreign_test_write'));
    $this->assertTrue($this->wroteSinceLastLook());

    $outcome = $this->runTool('foreign_test_write');
    $this->assertInstanceOf(ToolRefusal::class, $outcome);
    $this->assertSame(ToolRefusal::LIMIT, $outcome->reason);
    $this->assertStringContainsStringIgnoringCase('rate limit', $outcome->message);
    $this->assertSame('RATE_LIMIT_EXCEEDED', $outcome->code);
    $this->assertGreaterThan(0, $outcome->retryAfter);
    $this->assertLessThanOrEqual(60, $outcome->retryAfter);
    $this->assertFalse($this->wroteSinceLastLook(), 'The refused call did not run.');

    // Reading is never limited.
    for ($i = 0; $i < 5; $i++) {
      $this->assertInstanceOf(ToolInvocation::class, $this->runTool('foreign_test_read'));
    }
  }

  /**
   * A call the tool itself refuses is given back to the limit.
   */
  public function testACallThatDidNotRunIsNotCounted(): void {
    $this->config('mcp_tools.settings')
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_writes_per_minute', 1)
      ->save();

    foreach ([1, 2, 3] as $n) {
      $outcome = $this->runTool('foreign_test_forbidden');
      $this->assertInstanceOf(ToolInvocation::class, $outcome, "Tool API answers call $n, not the limit.");
      $this->assertFalse($outcome->hasExecuted());
    }
    $this->assertInstanceOf(ToolInvocation::class, $this->runTool('foreign_test_write'), 'The one write this minute allows still runs.');
    $this->assertInstanceOf(ToolRefusal::class, $this->runTool('foreign_test_write'));
  }

  /**
   * A write that ran leaves one audit line. A read, or a refusal, none.
   */
  public function testTheAuditLine(): void {
    $this->runTool('foreign_test_read');
    $this->runTool('foreign_test_forbidden');
    $this->assertSame([], $this->auditLines());

    $this->runTool('foreign_test_write');
    $lines = $this->auditLines();
    $this->assertCount(1, $lines, implode("\n", $lines));
    $this->assertStringContainsString('run_write_tool on tool "foreign_test_write"', $lines[0]);
  }

  /**
   * A call that ran is answered as it ran, also when the audit log fails.
   *
   * The tool has written by then. An answer of "failed" would have the
   * caller run it again.
   */
  public function testAnAuditLogThatCannotBeWrittenDoesNotFailTheCall(): void {
    $this->container->get('database')->schema()->dropTable('watchdog');
    $errorLog = (string) tempnam(sys_get_temp_dir(), 'mcpt');
    ini_set('error_log', $errorLog);

    $outcome = $this->runTool('foreign_test_write');
    $this->assertInstanceOf(ToolInvocation::class, $outcome);
    $this->assertTrue($outcome->getResult()->isSuccess());
    $this->assertTrue($this->wroteSinceLastLook());
    // And it is not lost without a word: PHP's error log has it.
    $this->assertStringContainsString('no audit line could be written for the tool foreign_test_write', (string) file_get_contents($errorLog));
    unlink($errorLog);
  }

  /**
   * The three steps, for a caller that does not run the tool itself.
   *
   * A caller in front of someone else's server (MCP Server's HTTP stack)
   * can let a call in or refuse it, and learns the outcome later.
   */
  public function testTheStepsOneByOne(): void {
    $this->config('mcp_tools.settings')
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_writes_per_minute', 1)
      ->save();
    $policy = $this->container->get('mcp_tools.tool_policy');
    $write = $this->container->get('plugin.manager.tool')->getDefinition('foreign_test_write');
    $read = $this->container->get('plugin.manager.tool')->getDefinition('foreign_test_read');

    $this->assertNull($policy->admit($read));
    $this->assertNull($policy->admit($write), 'The first write is let in.');
    $refusal = $policy->admit($write);
    $this->assertInstanceOf(ToolRefusal::class, $refusal);
    $this->assertSame(ToolRefusal::LIMIT, $refusal->reason);

    // The first call turns out not to have run: its count comes back.
    $policy->giveBack($write);
    $this->assertNull($policy->admit($write), 'There is room again.');
    $this->assertSame([], $this->auditLines(), 'Letting a call in writes no audit line.');

    // It ran: one audit line, with the outcome.
    $policy->record($write, ['title' => 'x'], FALSE);
    $lines = $this->auditLines();
    $this->assertCount(1, $lines, implode("\n", $lines));
    $this->assertStringContainsString('run_write_tool on tool "foreign_test_write"', $lines[0]);

    // Read-only mode refuses at the first step.
    $this->config('mcp_tools.settings')->set('access.read_only_mode', TRUE)->save();
    $refusal = $policy->admit($write);
    $this->assertInstanceOf(ToolRefusal::class, $refusal);
    $this->assertSame(ToolRefusal::ACCESS, $refusal->reason);
    $this->assertNull($policy->admit($read));

    // A tool of ours is never judged here: it carries the rules itself.
    $ours = $this->container->get('plugin.manager.tool')->getDefinition('mcp_cache_clear_all');
    $this->assertNull($policy->admit($ours));
  }

  /**
   * "Ours" goes by what a tool is, not by what its module is called.
   *
   * A module of someone else can be named mcp_tools_something and ship
   * plain Tool API tools. Those know none of our rules, so they are held to
   * them like any other: the audit log used to go by the provider name and
   * left such a tool out, while the limits counted it.
   */
  public function testAToolIsOursByItsClassNotByItsModuleName(): void {
    $definition = clone $this->container->get('plugin.manager.tool')->getDefinition('foreign_test_write');
    $definition->set('provider', 'mcp_tools_by_someone_else');
    $policy = $this->container->get('mcp_tools.tool_policy');

    $this->assertFalse(McpToolsToolBase::owns($definition));
    $policy->record($definition, [], TRUE);
    $lines = $this->auditLines();
    $this->assertCount(1, $lines, 'A plain Tool API tool is audited, whatever its module is called.');
    $this->assertStringContainsString('"provider":"mcp_tools_by_someone_else"', $lines[0]);

    // And one of ours is ours.
    $ours = $this->container->get('plugin.manager.tool')->getDefinition('mcp_cache_clear_all');
    $this->assertTrue(McpToolsToolBase::owns($ours));
    $policy->record($ours, [], TRUE);
    $this->assertCount(1, $this->auditLines(), 'A tool of ours writes its own audit line, from inside its service.');
  }

  /**
   * MCP Tools' own tools carry these rules themselves and are left alone.
   */
  public function testOurOwnToolsAreNotJudgedTwice(): void {
    $this->setUpCurrentUser([], ['mcp_tools use cache']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read']);

    // A read session: the tool's own access check refuses, through Tool API.
    $outcome = $this->runTool('mcp_cache_clear_all');
    $this->assertInstanceOf(ToolInvocation::class, $outcome);
    $this->assertFalse($outcome->hasExecuted());
    $this->assertFalse($outcome->getResult()->isSuccess());
  }

  /**
   * A caller can run a tool without the rules, as code of the site does.
   */
  public function testWithoutTheRules(): void {
    $this->config('mcp_tools.settings')->set('access.read_only_mode', TRUE)->save();

    $outcome = $this->runTool('foreign_test_write', FALSE);
    $this->assertInstanceOf(ToolInvocation::class, $outcome);
    $this->assertTrue($this->wroteSinceLastLook());
    $this->assertSame([], $this->auditLines());
  }

  /**
   * A destructive call that nobody confirmed is refused on any way in.
   *
   * The question was asked in MCP Tools' own server only. Through MCP
   * Server's bridge, through Drupal AI and for any code that runs a tool
   * through this policy, a destructive tool just ran, whatever the site
   * had set. A new site insists on a yes, and that holds here now.
   */
  public function testAStrongCallNobodyConfirmedIsRefusedWhereTheSiteInsists(): void {
    $state = $this->container->get('state');
    $state->set('foreign_tool_test.wrote', TRUE);

    // What a new site gets.
    $installed = $this->container->get('config.storage')->read('mcp_tools.settings');
    $this->assertNotFalse($installed);
    $shipped = \Drupal\Core\Serialization\Yaml::decode((string) file_get_contents($this->container->get('extension.list.module')->getPath('mcp_tools') . '/config/install/mcp_tools.settings.yml'));
    $this->assertTrue($shipped['access']['confirm_destructive_tools'], 'A new site does not insist on a yes.');
    $this->config('mcp_tools.settings')->set('access.confirm_destructive_tools', TRUE)->save();

    $refused = $this->runTool('foreign_test_delete');
    $this->assertInstanceOf(ToolRefusal::class, $refused, 'A destructive tool ran with nobody asked.');
    $this->assertSame(ToolRefusal::CONFIRMATION, $refused->reason);
    $this->assertSame('CONFIRMATION_REQUIRED', $refused->code);
    $this->assertStringContainsString('foreign_test_delete', $refused->message);
    $this->assertStringContainsString('do not call it again', $refused->message);
    $this->assertTrue((bool) $state->get('foreign_tool_test.wrote'), 'The tool ran.');

    // The first step alone says the same: MCP Server's bridge calls it.
    $definition = $this->container->get('plugin.manager.tool')->getDefinition('foreign_test_delete');
    $this->assertSame(ToolRefusal::CONFIRMATION, $this->container->get('mcp_tools.tool_policy')->admit($definition, [])?->reason);

    // A write that does not declare itself destructive is not held back.
    $this->assertInstanceOf(ToolInvocation::class, $this->runTool('foreign_test_write'));
    // A session that may not run the tool is refused for that.
    $this->container->get('mcp_tools.access_manager')->setScopes(['read']);
    $this->assertSame(ToolRefusal::ACCESS, $this->runTool('foreign_test_delete')->reason);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write', 'admin']);

    // After a person said yes, it runs.
    $state->set('foreign_tool_test.wrote', TRUE);
    $this->container->get('mcp_tools.tool_call_context')->markConfirmedByUser();
    $ran = $this->runTool('foreign_test_delete');
    $this->assertInstanceOf(ToolInvocation::class, $ran);
    $this->assertTrue($ran->hasExecuted());
    $this->assertFalse((bool) $state->get('foreign_tool_test.wrote'));

    // And on a site that does not insist, it runs as before.
    $this->container->get('mcp_tools.tool_call_context')->enter();
    $this->container->get('mcp_tools.tool_call_context')->leave();
    $this->config('mcp_tools.settings')->set('access.confirm_destructive_tools', FALSE)->save();
    $state->set('foreign_tool_test.wrote', TRUE);
    $this->assertInstanceOf(ToolInvocation::class, $this->runTool('foreign_test_delete'));
    $this->assertFalse((bool) $state->get('foreign_tool_test.wrote'));

    // Nor is a cache clear on a site that insists: it loses nothing a site
    // cannot rebuild. Last, because it rebuilds the container.
    $this->config('mcp_tools.settings')->set('access.confirm_destructive_tools', TRUE)->save();
    $caller = $this->container->get('current_user')->getAccount();
    $this->setUpCurrentUser([], ['mcp_tools use cache']);
    $cleared = $this->runTool('mcp_cache_clear_all');
    $this->container->get('current_user')->setAccount($caller);
    $this->assertInstanceOf(ToolInvocation::class, $cleared, 'A cache clear needs a yes.');
    $this->assertTrue($cleared->hasExecuted(), (string) $cleared->getResult()->getMessage());
  }

}
