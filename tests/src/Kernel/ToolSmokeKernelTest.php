<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolManager;

/**
 * Smoke tests for MCP Tools Tool API plugins.
 *
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ToolSmokeKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // MCP Tools (base + all core-only submodules).
    'mcp_tools',
    'mcp_tools_analysis',
    'mcp_tools_batch',
    'mcp_tools_blocks',
    'mcp_tools_cache',
    'mcp_tools_config',
    'mcp_tools_content',
    'mcp_tools_cron',
    'mcp_tools_image_styles',
    'mcp_tools_layout_builder',
    'mcp_tools_media',
    'mcp_tools_menus',
    'mcp_tools_migration',
    'mcp_tools_moderation',
    'mcp_tools_recipes',
    'mcp_tools_structure',
    'mcp_tools_templates',
    'mcp_tools_theme',
    'mcp_tools_users',
    'mcp_tools_views',

    // Required dependencies (core + Tool API).
    'tool',
    'block',
    'content_moderation',
    'dblog',
    'field',
    'file',
    'image',
    'layout_builder',
    'layout_discovery',
    'media',
    'menu_link_content',
    'node',
    'system',
    'taxonomy',
    'update',
    'user',
    'views',
    'workflows',
  ];

  /**
   * Verifies all core tools can be instantiated and access() does not throw.
   */
  public function testCoreToolsInstantiateAndAccessDoesNotThrow(): void {
    $this->installConfig(['mcp_tools']);

    $toolManager = $this->container->get('plugin.manager.tool');
    $this->assertInstanceOf(ToolManager::class, $toolManager);

    $definitions = $toolManager->getDefinitions();
    $mcpDefinitions = array_filter(
      $definitions,
      static fn(mixed $definition): bool => $definition instanceof ToolDefinition && str_starts_with($definition->getProvider(), 'mcp_tools')
    );

    // Base module + core-only submodules provide 154 tools.
    $this->assertCount(154, $mcpDefinitions);

    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);

    foreach (array_keys($mcpDefinitions) as $toolId) {
      $tool = $toolManager->createInstance($toolId);
      $this->assertInstanceOf(McpToolsToolBase::class, $tool, $toolId);
      $this->assertIsBool($tool->access($account), $toolId);
    }
  }

}
