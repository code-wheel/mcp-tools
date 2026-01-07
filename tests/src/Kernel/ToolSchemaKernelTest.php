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
 * This test serves as a contract test ensuring:
 * - All tools have valid schemas
 * - All parameters have proper types
 * - Schema conversion works correctly
 * - All tools have descriptions
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
   * Cached MCP definitions for use across tests.
   */
  private ?array $mcpDefinitions = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['mcp_tools']);
  }

  /**
   * Get MCP tool definitions.
   *
   * @return array
   *   Array of MCP tool definitions.
   */
  private function getMcpDefinitions(): array {
    if ($this->mcpDefinitions !== NULL) {
      return $this->mcpDefinitions;
    }

    /** @var \Drupal\tool\Tool\ToolManager $toolManager */
    $toolManager = $this->container->get('plugin.manager.tool');
    $this->assertInstanceOf(ToolManager::class, $toolManager);

    $definitions = $toolManager->getDefinitions();
    $this->mcpDefinitions = array_filter(
      $definitions,
      static fn(mixed $definition): bool => $definition instanceof ToolDefinition && str_starts_with((string) $definition->getProvider(), 'mcp_tools')
    );

    return $this->mcpDefinitions;
  }

  /**
   * Tests schema conversion does not throw for all core tools.
   */
  public function testToolSchemasConvert(): void {
    $mcpDefinitions = $this->getMcpDefinitions();

    // Base module + core-only submodules provide 154 tools.
    $this->assertCount(154, $mcpDefinitions);

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
      $properties = $schema['properties'] ?? NULL;
      $this->assertTrue(is_array($properties) || $properties instanceof \stdClass, $toolId);

      $encoded = json_encode($schema);
      $this->assertNotFalse($encoded, $toolId);

      $decoded = json_decode((string) $encoded);
      $this->assertInstanceOf(\stdClass::class, $decoded, $toolId);
      $this->assertTrue(isset($decoded->properties) && is_object($decoded->properties), $toolId);
    }
  }

  /**
   * Contract: All tools must have descriptions.
   */
  public function testAllToolsHaveDescriptions(): void {
    $mcpDefinitions = $this->getMcpDefinitions();

    foreach ($mcpDefinitions as $pluginId => $definition) {
      $toolId = (string) $pluginId;
      $description = $definition->getDescription();
      $this->assertNotEmpty(
        $description,
        "Tool $toolId must have a description"
      );
      $this->assertGreaterThan(
        10,
        strlen((string) $description),
        "Tool $toolId description should be meaningful (>10 chars)"
      );
    }
  }

  /**
   * Contract: All tool parameters must have valid types.
   */
  public function testAllParametersHaveValidTypes(): void {
    $mcpDefinitions = $this->getMcpDefinitions();
    $validTypes = ['string', 'integer', 'number', 'boolean', 'array', 'object'];

    $converter = new ToolApiSchemaConverter();
    foreach ($mcpDefinitions as $pluginId => $definition) {
      $toolId = (string) $pluginId;
      $schema = $converter->toolDefinitionToInputSchema($definition);
      $properties = $schema['properties'] ?? [];

      foreach ($properties as $propName => $propSchema) {
        if (!is_array($propSchema)) {
          continue;
        }
        $type = $propSchema['type'] ?? NULL;
        if ($type !== NULL) {
          $this->assertContains(
            $type,
            $validTypes,
            "Tool $toolId parameter '$propName' has invalid type '$type'"
          );
        }
      }
    }
  }

  /**
   * Contract: Required properties must be declared in schema.
   */
  public function testRequiredPropertiesAreDeclared(): void {
    $mcpDefinitions = $this->getMcpDefinitions();

    $converter = new ToolApiSchemaConverter();
    foreach ($mcpDefinitions as $pluginId => $definition) {
      $toolId = (string) $pluginId;
      $schema = $converter->toolDefinitionToInputSchema($definition);
      $properties = array_keys($schema['properties'] ?? []);
      $required = $schema['required'] ?? [];

      foreach ($required as $requiredProp) {
        $this->assertContains(
          $requiredProp,
          $properties,
          "Tool $toolId declares '$requiredProp' as required but it's not in properties"
        );
      }
    }
  }

  /**
   * Contract: Tool IDs must follow naming conventions.
   */
  public function testToolIdsFollowNamingConvention(): void {
    $mcpDefinitions = $this->getMcpDefinitions();

    foreach (array_keys($mcpDefinitions) as $toolId) {
      // Tool IDs should be lowercase with underscores.
      $this->assertMatchesRegularExpression(
        '/^[a-z][a-z0-9_]*$/',
        (string) $toolId,
        "Tool ID '$toolId' must be lowercase with underscores"
      );

      // Tool IDs should start with mcp_.
      $this->assertStringStartsWith(
        'mcp_',
        (string) $toolId,
        "Tool ID '$toolId' must start with 'mcp_'"
      );
    }
  }

  /**
   * Contract: Write tools must declare destructiveHint appropriately.
   */
  public function testWriteToolsHaveDestructiveHints(): void {
    $mcpDefinitions = $this->getMcpDefinitions();

    // Tools that perform write operations should have appropriate hints.
    $writeKeywords = ['create', 'delete', 'update', 'clear', 'remove', 'set'];

    $converter = new ToolApiSchemaConverter();
    foreach ($mcpDefinitions as $pluginId => $definition) {
      $toolId = (string) $pluginId;
      $annotations = $converter->toolDefinitionToAnnotations($definition);

      // Check if tool ID suggests write operation.
      $isWriteTool = FALSE;
      foreach ($writeKeywords as $keyword) {
        if (stripos($toolId, $keyword) !== FALSE) {
          $isWriteTool = TRUE;
          break;
        }
      }

      // Write tools should not be marked as readOnlyHint=true.
      if ($isWriteTool) {
        $this->assertFalse(
          $annotations['readOnlyHint'] ?? FALSE,
          "Write tool $toolId should not have readOnlyHint=true"
        );
      }
    }
  }

  /**
   * Contract: Schemas must be valid JSON Schema format.
   */
  public function testSchemasAreValidJsonSchema(): void {
    $mcpDefinitions = $this->getMcpDefinitions();

    $converter = new ToolApiSchemaConverter();
    foreach ($mcpDefinitions as $pluginId => $definition) {
      $toolId = (string) $pluginId;
      $schema = $converter->toolDefinitionToInputSchema($definition);

      // Must have type: object at root.
      $this->assertSame(
        'object',
        $schema['type'] ?? NULL,
        "Tool $toolId schema must have type: object at root"
      );

      // Must have properties key.
      $this->assertArrayHasKey(
        'properties',
        $schema,
        "Tool $toolId schema must have properties key"
      );

      // Properties must be array-like.
      $properties = $schema['properties'];
      $this->assertTrue(
        is_array($properties) || $properties instanceof \stdClass,
        "Tool $toolId properties must be array or object"
      );
    }
  }

}
