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
 * Tool for rebuilding specific caches.
 *
 * @Tool(
 *   id = "mcp_cache_rebuild",
 *   label = @Translation("Rebuild Cache"),
 *   description = @Translation("Rebuild a specific cache type (router, theme, container, menu)."),
 *   category = "cache",
 * )
 */
class RebuildCache extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $accessCheck = $this->accessManager->checkWriteAccess('rebuild', 'cache');
    if (!$accessCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $accessCheck['reason'],
        'code' => $accessCheck['code'] ?? 'ACCESS_DENIED',
      ];
    }

    $type = $input['type'] ?? '';
    if (empty($type)) {
      return [
        'success' => FALSE,
        'error' => 'type is required. Valid types: router, theme, container, menu.',
      ];
    }

    $result = $this->cacheService->rebuild($type);

    if ($result['success']) {
      $this->auditLogger->log('rebuild', 'cache', $type, []);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'type' => [
        'type' => 'string',
        'label' => 'Rebuild Type',
        'description' => 'What to rebuild: router, theme, container, or menu.',
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
