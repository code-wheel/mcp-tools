<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_cache\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_cache\Service\CacheService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for clearing all caches.
 *
 * @Tool(
 *   id = "mcp_cache_clear_all",
 *   label = @Translation("Clear All Caches"),
 *   description = @Translation("Clear all Drupal caches (equivalent to drush cr)."),
 *   category = "cache",
 * )
 */
class ClearAllCaches extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected CacheService $cacheService;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->cacheService = $container->get('mcp_tools_cache.cache_service');
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    $instance->auditLogger = $container->get('mcp_tools.audit_logger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    // Check write access (cache clear is a write operation).
    $accessCheck = $this->accessManager->checkWriteAccess('clear', 'cache');
    if (!$accessCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $accessCheck['reason'],
        'code' => $accessCheck['code'] ?? 'ACCESS_DENIED',
      ];
    }

    $result = $this->cacheService->clearAllCaches();

    if ($result['success']) {
      $this->auditLogger->log('clear', 'cache', 'all', []);
    }

    return $result;
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
      'success' => [
        'type' => 'boolean',
        'label' => 'Success status',
      ],
      'message' => [
        'type' => 'string',
        'label' => 'Result message',
      ],
    ];
  }

}
