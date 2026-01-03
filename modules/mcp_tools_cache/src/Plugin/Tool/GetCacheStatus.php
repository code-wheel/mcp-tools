<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_cache\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_cache\Service\CacheService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting cache status.
 *
 * @Tool(
 *   id = "mcp_cache_get_status",
 *   label = @Translation("Get Cache Status"),
 *   description = @Translation("Get overview of cache bins and their status."),
 *   category = "cache",
 * )
 */
class GetCacheStatus extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected CacheService $cacheService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->cacheService = $container->get('mcp_tools_cache.cache_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    return [
      'success' => TRUE,
      'data' => $this->cacheService->getCacheStatus(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total_bins' => [
        'type' => 'integer',
        'label' => 'Total cache bins',
      ],
      'bins' => [
        'type' => 'list',
        'label' => 'Cache bin details',
      ],
    ];
  }

}
