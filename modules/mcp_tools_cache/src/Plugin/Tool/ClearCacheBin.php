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
 * Tool for clearing a specific cache bin.
 *
 * @Tool(
 *   id = "mcp_cache_clear_bin",
 *   label = @Translation("Clear Cache Bin"),
 *   description = @Translation("Clear a specific cache bin (e.g., render, page, entity)."),
 *   category = "cache",
 * )
 */
class ClearCacheBin extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    // Check write access.
    $accessCheck = $this->accessManager->checkWriteAccess('clear', 'cache');
    if (!$accessCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $accessCheck['reason'],
        'code' => $accessCheck['code'] ?? 'ACCESS_DENIED',
      ];
    }

    $bin = $input['bin'] ?? '';
    if (empty($bin)) {
      return [
        'success' => FALSE,
        'error' => 'bin is required.',
      ];
    }

    $result = $this->cacheService->clearCacheBin($bin);

    if ($result['success']) {
      $this->auditLogger->log('clear', 'cache', $bin, []);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'bin' => [
        'type' => 'string',
        'label' => 'Cache Bin',
        'description' => 'The cache bin to clear (e.g., render, page, entity, menu).',
        'required' => TRUE,
      ],
    ];
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
