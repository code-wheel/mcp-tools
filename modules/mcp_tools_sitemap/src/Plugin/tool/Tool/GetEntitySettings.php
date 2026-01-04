<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_sitemap\Plugin\tool\Tool;

use Drupal\mcp_tools_sitemap\Service\SitemapService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_sitemap_entity_settings',
  label: new TranslatableMarkup('Get Entity Sitemap Settings'),
  description: new TranslatableMarkup('Get sitemap inclusion settings for an entity type and optionally a specific bundle.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type ID (e.g., "node", "taxonomy_term", "media").'),
      required: TRUE,
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('Optional: specific bundle to get settings for (e.g., "article", "page").'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type ID that was queried (e.g., "node", "taxonomy_term").'),
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('The specific bundle queried, or NULL if all bundles were requested.'),
    ),
    'settings' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Sitemap Settings'),
      description: new TranslatableMarkup('Sitemap settings for the bundle: index (bool), priority (0.0-1.0), changefreq, include_images. Use SetEntitySettings to modify.'),
    ),
    'bundles' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('All Bundle Settings'),
      description: new TranslatableMarkup('When no bundle specified, returns all bundle settings for the entity type. Keys are bundle IDs, values are settings objects.'),
    ),
  ],
)]
class GetEntitySettings extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'sitemap';


  protected SitemapService $sitemapService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->sitemapService = $container->get('mcp_tools_sitemap.sitemap');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $entityType = $input['entity_type'] ?? '';
    $bundle = $input['bundle'] ?? NULL;

    if (empty($entityType)) {
      return ['success' => FALSE, 'error' => 'Entity type is required.'];
    }

    return $this->sitemapService->getEntitySettings($entityType, $bundle);
  }

  

  

}
