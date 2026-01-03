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
 * Tool for clearing entity cache.
 *
 * @Tool(
 *   id = "mcp_cache_clear_entity",
 *   label = @Translation("Clear Entity Cache"),
 *   description = @Translation("Clear render cache for a specific entity."),
 *   category = "cache",
 * )
 */
class ClearEntityCache extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    $entityType = $input['entity_type'] ?? '';
    $entityId = $input['entity_id'] ?? '';

    if (empty($entityType) || empty($entityId)) {
      return [
        'success' => FALSE,
        'error' => 'Both entity_type and entity_id are required.',
      ];
    }

    $result = $this->cacheService->clearEntityCache($entityType, $entityId);

    if ($result['success']) {
      $this->auditLogger->log('clear', 'entity_cache', "$entityType:$entityId", [
        'entity_type' => $entityType,
        'entity_id' => $entityId,
      ]);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'entity_type' => [
        'type' => 'string',
        'label' => 'Entity Type',
        'description' => 'The entity type (e.g., node, user, taxonomy_term).',
        'required' => TRUE,
      ],
      'entity_id' => [
        'type' => 'string',
        'label' => 'Entity ID',
        'description' => 'The entity ID.',
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
      'invalidated_tags' => [
        'type' => 'array',
        'label' => 'Invalidated cache tags',
      ],
    ];
  }

}
