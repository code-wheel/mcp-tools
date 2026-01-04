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
  id: 'mcp_sitemap_get_settings',
  label: new TranslatableMarkup('Get Sitemap Settings'),
  description: new TranslatableMarkup('Get settings for a specific sitemap variant including global settings and entity type configuration.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'variant' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Sitemap Variant'),
      description: new TranslatableMarkup('The sitemap variant ID (defaults to "default").'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'variant' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Variant ID'),
      description: new TranslatableMarkup('The sitemap variant ID (e.g., "default"). Use with UpdateSettings or Regenerate.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Variant Label'),
      description: new TranslatableMarkup('Human-readable name for this sitemap variant.'),
    ),
    'global_settings' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Global Settings'),
      description: new TranslatableMarkup('Global sitemap settings: max_links, cron_generate, base_url, and other configuration. Use UpdateSettings to modify.'),
    ),
    'entity_types' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Entity Type Settings'),
      description: new TranslatableMarkup('Per-entity-type settings with bundle configurations. Keys are entity type IDs. Use GetEntitySettings for detailed bundle info.'),
    ),
  ],
)]
class GetSettings extends McpToolsToolBase {

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
    $variant = $input['variant'] ?? 'default';
    return $this->sitemapService->getSettings($variant);
  }

  

  

}
