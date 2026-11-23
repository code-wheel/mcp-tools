<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tool\Tool\Invoker;
use Drupal\user\Entity\Role;

/**
 * A strong tool of ours refuses a caller that is not a person and asked no one.
 *
 * Our server and ToolPolicy ask first, or refuse. A tool of ours can also be
 * called by MCP Server's tool bridge without mcp_tools_policy, by Tool API's
 * own AI connector, or by another module's agent. Tool API tells the tool
 * who called it. On a site that insists on a yes, the tool refuses such a
 * caller itself.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_structure')]
final class InvokerRuleKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_structure'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['mcp_tools']);
    $this->createUser();
    $this->setUpCurrentUser([], ['mcp_tools use structure', 'administer permissions']);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->set('access.confirm_destructive_tools', TRUE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * Deletes a role through Tool API's runner, for one invoker.
   *
   * @return array{0: bool, 1: string}
   *   Whether the role is gone, and what the tool said.
   */
  private function deleteAs(?string $invoker): array {
    Role::load('temp')?->delete();
    Role::create(['id' => 'temp', 'label' => 'Temp'])->save();
    $invocation = $this->container->get('tool.runner')->run('mcp_structure_delete_role', ['id' => 'temp'], [], $invoker === NULL ? NULL : new Invoker($invoker));
    $this->container->get('entity_type.manager')->getStorage('user_role')->resetCache();
    return [Role::load('temp') === NULL, (string) $invocation->getResult()->getMessage()];
  }

  /**
   * Callers that are not a person, and asked no one: refused.
   */
  public function testACallerThatAskedNoOneIsRefused(): void {
    foreach (['mcp_server_tool_bridge', 'tool_ai_connector', 'some_agent'] as $invoker) {
      [$gone, $message] = $this->deleteAs($invoker);
      $this->assertFalse($gone, $invoker . ' deleted a role with nobody asked.');
      $this->assertStringContainsString('only after a person confirmed it', $message, $invoker);
      $this->assertStringContainsString($invoker, $message);
    }
  }

  /**
   * A person (drush, a form, the site's code) and a yes: it runs.
   */
  public function testAPersonOrAYesRuns(): void {
    foreach ([NULL, 'php', 'drush'] as $invoker) {
      [$gone, $message] = $this->deleteAs($invoker);
      $this->assertTrue($gone, var_export($invoker, TRUE) . ': ' . $message);
    }

    // A caller that asked, and the person said yes.
    $this->container->get('mcp_tools.tool_call_context')->enter();
    $this->container->get('mcp_tools.tool_call_context')->markConfirmedByUser();
    [$gone, $message] = $this->deleteAs('mcp_server_tool_bridge');
    $this->container->get('mcp_tools.tool_call_context')->leave();
    $this->assertTrue($gone, $message);

    // A site that does not insist runs it for anyone, as before.
    $this->config('mcp_tools.settings')->set('access.confirm_destructive_tools', FALSE)->save();
    [$gone, $message] = $this->deleteAs('mcp_server_tool_bridge');
    $this->assertTrue($gone, $message);
  }

  /**
   * A tool that destroys nothing is not held back for any caller.
   */
  public function testAReadIsNotHeldBack(): void {
    $invocation = $this->container->get('tool.runner')->run('mcp_structure_list_roles', [], [], new Invoker('mcp_server_tool_bridge'));
    $this->assertTrue($invocation->getResult()->isSuccess(), (string) $invocation->getResult()->getMessage());
  }

}
