<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_cache\Plugin\tool\Tool;

use Drupal\mcp_tools_cache\Service\CacheService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_cache_get_status',
  label: new TranslatableMarkup('Get Cache Status'),
  description: new TranslatableMarkup('Get overview of cache bins and their status.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'cache_tags_table_size' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Cache tag rows'),
      description: new TranslatableMarkup('Rows in the cache tag table.'),
      required: FALSE,
    ),
    'total_bins' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total cache bins'),
      description: new TranslatableMarkup('Number of cache bins in the system.'),
    ),
    'bins' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Cache bin details'),
      description: new TranslatableMarkup('Array of cache bins with bin, backend, and estimated_items. Give the bin to mcp_cache_clear_bin.'),
    ),
  ],
)]
class GetCacheStatus extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'cache';


  /**
   * The cache service.
   *
   * @var \Drupal\mcp_tools_cache\Service\CacheService
   */
  protected CacheService $cacheService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->cacheService = $container->get('mcp_tools_cache.cache_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    // The service's answer as it is: wrapped in another "data", every
    // declared output was empty and the tool said success.
    return $this->cacheService->getCacheStatus();
  }

}
