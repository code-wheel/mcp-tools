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
  id: 'mcp_sitemap_set_entity',
  label: new TranslatableMarkup('Set Entity Sitemap Settings'),
  description: new TranslatableMarkup('Set sitemap inclusion settings for an entity type bundle. This is a write operation.'),
  operation: ToolOperation::Write,
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
      description: new TranslatableMarkup('The bundle ID (e.g., "article", "page", "tags").'),
      required: TRUE,
    ),
    'settings' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Settings'),
      description: new TranslatableMarkup('Sitemap settings: index (bool), priority (0.0-1.0), changefreq (always/hourly/daily/weekly/monthly/yearly/never), include_images (bool).'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup(''),
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup(''),
    ),
    'settings' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Applied Settings'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class SetEntitySettings extends McpToolsToolBase {

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
    $bundle = $input['bundle'] ?? '';
    $settings = $input['settings'] ?? [];

    if (empty($entityType)) {
      return ['success' => FALSE, 'error' => 'Entity type is required.'];
    }

    if (empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Bundle is required.'];
    }

    if (empty($settings)) {
      return ['success' => FALSE, 'error' => 'Settings object is required.'];
    }

    return $this->sitemapService->setEntitySettings($entityType, $bundle, $settings);
  }

  

  

}
