<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_cache\Plugin\tool\Tool;

use Drupal\mcp_tools_cache\Service\CacheService;
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
  id: 'mcp_cache_get_status',
  label: new TranslatableMarkup('Get Cache Status'),
  description: new TranslatableMarkup('Get overview of cache bins and their status.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total_bins' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total cache bins'),
      description: new TranslatableMarkup(''),
    ),
    'bins' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Cache bin details'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GetCacheStatus extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'cache';


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
    return [
      'success' => TRUE,
      'data' => $this->cacheService->getCacheStatus(),
    ];
  }

  

  

}
