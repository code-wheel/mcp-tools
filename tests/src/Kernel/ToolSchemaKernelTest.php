<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolManager;

/**
 * Ensures all core tool definitions can be converted to MCP schemas.
 *
 * @group mcp_tools
 */
final class ToolSchemaKernelTest extends KernelTestBase {

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
   * Tests schema conversion does not throw for all core tools.
   */
  public function testToolSchemasConvert(): void {
    $this->installConfig(['mcp_tools']);

    /** @var \Drupal\tool\Tool\ToolManager $toolManager */
    $toolManager = $this->container->get('plugin.manager.tool');
    $this->assertInstanceOf(ToolManager::class, $toolManager);

    $definitions = $toolManager->getDefinitions();
    $mcpDefinitions = array_filter(
      $definitions,
      static fn(mixed $definition): bool => $definition instanceof ToolDefinition && str_starts_with((string) $definition->getProvider(), 'mcp_tools')
    );

    // Base module + core-only submodules provide 144 tools.
    $this->assertCount(144, $mcpDefinitions);

    $converter = new ToolApiSchemaConverter();
    foreach ($mcpDefinitions as $pluginId => $definition) {
      $this->assertInstanceOf(ToolDefinition::class, $definition);
      $toolId = (string) $pluginId;

      $annotations = $converter->toolDefinitionToAnnotations($definition);
      $this->assertArrayHasKey('title', $annotations, $toolId);
      $this->assertArrayHasKey('readOnlyHint', $annotations, $toolId);
      $this->assertArrayHasKey('openWorldHint', $annotations, $toolId);
      $this->assertArrayHasKey('destructiveHint', $annotations, $toolId);

      $schema = $converter->toolDefinitionToInputSchema($definition);
      $this->assertIsArray($schema, $toolId);
      $this->assertSame('object', $schema['type'] ?? NULL, $toolId);
      $this->assertIsArray($schema['properties'] ?? NULL, $toolId);

      $encoded = json_encode($schema);
      $this->assertNotFalse($encoded, $toolId);
    }
  }

}
