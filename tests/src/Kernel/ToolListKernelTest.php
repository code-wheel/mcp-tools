<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\McpToolsServerFactory;
use Drupal\mcp_tools\Mcp\ToolApiGateway;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolManager;
use Drupal\tool\Tool\ToolOperation;
use Psr\Log\NullLogger;

/**
 * The tool list is what the session may call.
 *
 * The server listed every tool and refused at the door: a read-only key was
 * shown more than a hundred write tools it could never run. The list now
 * follows the same rule as the door, asked without making the tools.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ToolListKernelTest extends KernelTestBase {

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
    $this->installConfig(['mcp_tools']);
    // The first account is uid 1, which passes every permission check. Keep
    // it out of the way so the tests below run as ordinary users.
    $this->createUser();
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write', 'admin'])
      ->set('access.read_only_mode', FALSE)
      ->set('access.config_only_mode', FALSE)
      ->save();
  }

  /**
   * Logs in a user who may use every MCP Tools category on this site.
   */
  private function loginWithAllCategories(): void {
    $this->setUpCurrentUser([], ['mcp_tools use site_health', 'mcp_tools use cache', 'mcp_tools use content', 'mcp_tools use config', 'mcp_tools use users', 'mcp_tools use structure', 'mcp_tools use discovery']);
  }

  /**
   * The names of the tools the session is shown.
   *
   * @return string[]
   *   MCP tool names.
   */
  private function listed(array $scopes): array {
    $access = $this->container->get('mcp_tools.access_manager');
    $access->setScopes($scopes);
    $names = [];
    foreach ($this->container->get('plugin.manager.tool')->getDefinitions() as $id => $definition) {
      if ($definition instanceof ToolDefinition && $access->listsTool($definition)) {
        $names[] = McpToolsServerFactory::pluginIdToMcpName((string) $id);
      }
    }
    return $names;
  }

  /**
   * A session without the write scope is not shown write tools.
   */
  public function testReadSessionIsNotShownWriteTools(): void {
    $this->loginWithAllCategories();

    $read = $this->listed(['read']);
    $this->assertContains('mcp_tools_get_site_status', $read);
    $this->assertContains('mcp_cache_get_status', $read);
    $this->assertContains('foreign_test_read', $read);
    $this->assertNotContains('mcp_cache_clear_all', $read);
    $this->assertNotContains('mcp_cache_rebuild', $read);
    $this->assertNotContains('foreign_test_write', $read);
    $this->assertNotContains('foreign_test_delete', $read);

    $write = $this->listed(['read', 'write']);
    $this->assertContains('mcp_cache_rebuild', $write);
    $this->assertContains('foreign_test_write', $write);
    $this->assertGreaterThan(count($read), count($write));
  }

  /**
   * Read-only mode takes the write tools off the list, whatever the scopes.
   */
  public function testReadOnlyModeHidesWriteTools(): void {
    $this->loginWithAllCategories();
    $this->config('mcp_tools.settings')->set('access.read_only_mode', TRUE)->save();

    $listed = $this->listed(['read', 'write', 'admin']);
    $this->assertContains('mcp_cache_get_status', $listed);
    $this->assertNotContains('mcp_cache_rebuild', $listed);
    $this->assertNotContains('foreign_test_write', $listed);
  }

  /**
   * A user without a category's permission is not shown its tools.
   */
  public function testPermissionDecidesOurTools(): void {
    $this->setUpCurrentUser([], ['mcp_tools use site_health']);

    $listed = $this->listed(['read', 'write', 'admin']);
    $this->assertContains('mcp_tools_get_site_status', $listed);
    $this->assertNotContains('mcp_cache_get_status', $listed);
    $this->assertNotContains('mcp_cache_rebuild', $listed);
  }

  /**
   * The site can ask for the whole list, as it was.
   */
  public function testListAllSetting(): void {
    $this->setUpCurrentUser([], ['mcp_tools use site_health']);
    $this->config('mcp_tools.settings')->set('access.list_all_tools', TRUE)->save();

    $listed = $this->listed(['read']);
    $this->assertContains('mcp_cache_rebuild', $listed);
    $this->assertContains('foreign_test_write', $listed);
  }

  /**
   * The list and the door agree on every tool, for every kind of session.
   *
   * A tool on the list that the door refuses wastes a call. A tool the door
   * would let through that is not on the list is a tool nobody can find.
   */
  public function testTheListAgreesWithTheDoor(): void {
    $this->loginWithAllCategories();
    $access = $this->container->get('mcp_tools.access_manager');
    $manager = $this->container->get('plugin.manager.tool');
    $checked = 0;

    foreach ([['read'], ['read', 'write'], ['read', 'write', 'admin'], ['write']] as $scopes) {
      foreach ([FALSE, TRUE] as $readOnly) {
        $this->config('mcp_tools.settings')->set('access.read_only_mode', $readOnly)->save();
        $access->setScopes($scopes);
        foreach ($manager->getDefinitions() as $id => $definition) {
          if (!$definition instanceof ToolDefinition) {
            continue;
          }
          // What decides at the door. A tool of ours: its own access
          // check. A tool of another provider: the permission it declares
          // and this session's scopes and modes. What such a tool decides
          // in its own access check is not known without making it, so
          // the list does not go by it (see the test below).
          if (is_a($definition->getClass(), McpToolsToolBase::class, TRUE)) {
            $door = $manager->createInstance($id)->access();
          }
          else {
            $door = ToolManager::checkPermission($definition, $this->container->get('current_user'))->isAllowed()
              && $access->allowsForeignToolOperation(($definition->getOperation() ?? ToolOperation::Transform)->value);
          }
          $this->assertSame($door, $access->listsTool($definition), sprintf('%s with scopes %s, read-only mode %s', $id, implode(',', $scopes), $readOnly ? 'on' : 'off'));
          $checked++;
        }
      }
    }
    $this->assertGreaterThan(200, $checked);
  }

  /**
   * A tool of another module is not hidden for a reason of its own.
   *
   * The test module has a write tool whose own access check refuses
   * everyone. It is listed for a session with the write scope, and the call
   * is refused. Hiding it would need the tool made for every list.
   */
  public function testOtherModulesOwnRefusalIsNotGuessed(): void {
    $this->loginWithAllCategories();
    $this->assertContains('foreign_test_forbidden', $this->listed(['read', 'write']));
    $this->assertNotContains('foreign_test_forbidden', $this->listed(['read']));
  }

  /**
   * A first question about the site finds the site status tool.
   *
   * "Which version of Drupal core does this site run" was the question a
   * local model failed most. It searched "core" and "drupal core". The
   * tool with the answer spoke of "version info" and was not among the
   * three examples of its group, so the model ended in the status report,
   * which is twenty thousand characters long.
   */
  public function testTheCoreVersionQuestionFindsTheSiteStatusTool(): void {
    $this->loginWithAllCategories();
    $access = $this->container->get('mcp_tools.access_manager');
    $access->setScopes(['read']);
    $gateway = new ToolApiGateway(
      $this->container->get('plugin.manager.tool'),
      new ToolApiSchemaConverter(),
      new NullLogger(),
      FALSE,
      'mcp_tools',
      NULL,
      NULL,
      $access,
    );

    // "site health" is what the server's own instructions call this group
    // of tools. A model that searched "health" found the cron tool only.
    foreach (['core', 'drupal core', 'core version', 'drupal version', 'php version', 'health', 'site health'] as $query) {
      $found = array_column($gateway->discoverTools($query, 5)->structuredContent['tools'], 'name');
      $this->assertContains('mcp_tools_get_site_status', $found, sprintf('searching "%s" found: %s', $query, implode(', ', $found)));
    }

    // And without a search: the overview names it.
    $overview = $gateway->discoverTools()->structuredContent;
    $this->assertSame('overview', $overview['mode']);
    $this->assertStringContainsString('mcp_tools_get_site_status', $overview['hint']);
  }

  /**
   * The gateway's find and describe tools do not pause the server.
   *
   * The server's SDK runs every handler in a Fiber. From mcp/sdk 0.10 it
   * fails a request whose handler pauses that Fiber for anything but the
   * SDK. Drupal pauses the current Fiber when it loads an entity.
   *
   * Since the tool list goes by what the session may call, find and
   * describe check access, and that loads the user when it is not in
   * memory: after a call that ended before its own access check (a tool
   * that does not exist, wrong arguments). From then on every search in
   * that session ended in "Internal server error".
   */
  public function testGatewayFindAndDescribeDoNotPauseTheServersFiber(): void {
    $this->loginWithAllCategories();
    $access = $this->container->get('mcp_tools.access_manager');
    $access->setScopes(['read', 'write']);
    $context = $this->container->get('mcp_tools.tool_call_context');
    $gateway = new ToolApiGateway(
      $this->container->get('plugin.manager.tool'),
      new ToolApiSchemaConverter(),
      new NullLogger(),
      FALSE,
      'mcp_tools',
      NULL,
      NULL,
      $access,
      $context,
    );
    $handlers = array_column($gateway->getGatewayTools(), 'handler', 'name');

    foreach ([
      ToolApiGateway::DISCOVER_TOOL => ['site status', 5],
      ToolApiGateway::GET_INFO_TOOL => ['mcp_tools_get_site_status'],
    ] as $name => $arguments) {
      // What a call leaves that starts and ends without an access check:
      // nothing in memory.
      $context->enter();
      $context->leave();

      $fiber = new \Fiber(static fn() => $handlers[$name](...$arguments));
      $fiber->start();
      $this->assertTrue($fiber->isTerminated(), $name . ' paused the Fiber it was called in.');
      $result = $fiber->getReturn();
      $this->assertFalse($result->isError, $name);
      $this->assertStringContainsString('mcp_tools_get_site_status', json_encode($result->structuredContent));
    }
  }

  /**
   * The server tells the model that tools are hidden, and why.
   */
  public function testInstructionsSayWhatIsHidden(): void {
    $this->assertStringNotContainsString('not listed', McpToolsServerFactory::instructions(FALSE));

    $text = McpToolsServerFactory::instructions(FALSE, 12);
    $this->assertStringContainsString('12', $text);
    $this->assertStringContainsString('not listed', $text);
  }

}
