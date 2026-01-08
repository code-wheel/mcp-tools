<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\Prompt\PromptRegistry;
use Drupal\mcp_tools\Mcp\Resource\ResourceRegistry;

/**
 * Contract tests for MCP resources and prompts.
 *
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ResourcePromptContractKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // MCP Tools (base + core-only submodules).
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
    'mcp_tools_observability',
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
   * Ensures resources and prompts have valid contracts.
   */
  public function testResourceAndPromptContracts(): void {
    $this->installConfig(['mcp_tools']);

    $resourceRegistry = $this->container->get('mcp_tools.resource_registry');
    $this->assertInstanceOf(ResourceRegistry::class, $resourceRegistry);

    $resources = $resourceRegistry->getResources();
    $this->assertNotEmpty($resources);

    $resourceUris = [];
    foreach ($resources as $resource) {
      $uri = $resource['uri'] ?? '';
      $handler = $resource['handler'] ?? NULL;

      $this->assertIsString($uri);
      $this->assertNotSame('', $uri);
      $this->assertTrue(is_callable($handler));
      $this->assertArrayNotHasKey($uri, $resourceUris, 'Duplicate resource uri: ' . $uri);
      $resourceUris[$uri] = TRUE;
    }

    $templates = $resourceRegistry->getResourceTemplates();
    $templateUris = [];
    foreach ($templates as $template) {
      $uriTemplate = $template['uriTemplate'] ?? '';
      $handler = $template['handler'] ?? NULL;

      $this->assertIsString($uriTemplate);
      $this->assertNotSame('', $uriTemplate);
      $this->assertTrue(is_callable($handler));
      $this->assertArrayNotHasKey($uriTemplate, $templateUris, 'Duplicate resource template uri: ' . $uriTemplate);
      $templateUris[$uriTemplate] = TRUE;
    }

    $promptRegistry = $this->container->get('mcp_tools.prompt_registry');
    $this->assertInstanceOf(PromptRegistry::class, $promptRegistry);

    $prompts = $promptRegistry->getPrompts();
    $this->assertNotEmpty($prompts);

    $promptNames = [];
    foreach ($prompts as $prompt) {
      $handler = $prompt['handler'] ?? NULL;
      $name = $prompt['name'] ?? NULL;

      $this->assertTrue(is_callable($handler));

      if ($name !== NULL) {
        $this->assertIsString($name);
        $this->assertNotSame('', $name);
        $this->assertArrayNotHasKey($name, $promptNames, 'Duplicate prompt name: ' . $name);
        $promptNames[$name] = TRUE;
      }
    }
  }

}
