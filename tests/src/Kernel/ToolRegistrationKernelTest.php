<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolManager;

/**
 * Verifies Tool API registration and access gating for MCP Tools.
 *
 * @group mcp_tools
 */
final class ToolRegistrationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mcp_tools',
    'mcp_tools_structure',
    'tool',
    'field',
    'taxonomy',
    'user',
    'system',
    'update',
    'dblog',
  ];

  /**
   * Tests Tool API discovers MCP Tools plugins.
   */
  public function testToolDefinitionsLoad(): void {
    $this->installConfig(['mcp_tools']);

    /** @var \Drupal\tool\Tool\ToolManager $toolManager */
    $toolManager = $this->container->get('plugin.manager.tool');
    $this->assertInstanceOf(ToolManager::class, $toolManager);

    $definitions = $toolManager->getDefinitions();
    $this->assertArrayHasKey('mcp_tools_get_site_status', $definitions);
    $this->assertInstanceOf(ToolDefinition::class, $definitions['mcp_tools_get_site_status']);
  }

  /**
   * Tests category permissions and scopes gate execution.
   */
  public function testToolAccessIsGated(): void {
    $this->installConfig(['mcp_tools']);

    /** @var \Drupal\tool\Tool\ToolManager $toolManager */
    $toolManager = $this->container->get('plugin.manager.tool');

    $readTool = $toolManager->createInstance('mcp_tools_get_site_status');
    $writeTool = $toolManager->createInstance('mcp_structure_create_content_type');

    // Provide required inputs so Tool API can validate access().
    $writeTool->setInputValue('id', 'mcp_test_type');
    $writeTool->setInputValue('label', 'MCP Test Type');
    $writeTool->setInputValue('description', '');
    $writeTool->setInputValue('create_body', FALSE);

    $noPerms = $this->createAccountMock([]);
    $siteHealthPerm = $this->createAccountMock(['mcp_tools use site_health']);
    $structurePerm = $this->createAccountMock(['mcp_tools use structure']);

    // Read tool: requires category permission.
    $this->assertFalse($readTool->access($noPerms));
    $this->assertTrue($readTool->access($siteHealthPerm));

    // Write tool: requires category permission and write scope.
    $accessManager = $this->container->get('mcp_tools.access_manager');
    $accessManager->setScopes([AccessManager::SCOPE_READ]);
    $this->assertFalse($writeTool->access($structurePerm));

    $accessManager->setScopes([AccessManager::SCOPE_READ, AccessManager::SCOPE_WRITE]);
    $this->assertTrue($writeTool->access($structurePerm));

    // Global read-only mode should block write tools even with scope + permission.
    $this->config('mcp_tools.settings')->set('access.read_only_mode', TRUE)->save();
    $this->assertFalse($writeTool->access($structurePerm));
  }

  /**
   * Creates an account mock that has the specified permissions.
   *
   * @param string[] $permissions
   *   Permissions granted to the account.
   */
  private function createAccountMock(array $permissions): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')
      ->willReturnCallback(static fn(string $permission): bool => in_array($permission, $permissions, TRUE));
    return $account;
  }

}
