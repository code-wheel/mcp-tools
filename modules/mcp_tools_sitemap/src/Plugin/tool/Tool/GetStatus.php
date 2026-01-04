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
  id: 'mcp_sitemap_status',
  label: new TranslatableMarkup('Get Sitemap Status'),
  description: new TranslatableMarkup('Get sitemap generation status including last generated time, link counts, and queue status.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'sitemaps' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Sitemap Status'),
      description: new TranslatableMarkup('Status for each sitemap variant: last_generated timestamp, link_count, chunk_count, file_size. Keys are variant IDs.'),
    ),
    'total_sitemaps' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Sitemaps'),
      description: new TranslatableMarkup('Total number of sitemap variants in the system.'),
    ),
    'generator_queue' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Generator Queue Status'),
      description: new TranslatableMarkup('Regeneration queue status: items_pending, is_running, last_run. Check after Regenerate to monitor progress.'),
    ),
  ],
)]
class GetStatus extends McpToolsToolBase {

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
    return $this->sitemapService->getStatus();
  }

  

  

}
