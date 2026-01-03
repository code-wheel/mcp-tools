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
 * Tool for invalidating cache tags.
 *
 * @Tool(
 *   id = "mcp_cache_invalidate_tags",
 *   label = @Translation("Invalidate Cache Tags"),
 *   description = @Translation("Invalidate specific cache tags to clear related cached data."),
 *   category = "cache",
 * )
 */
class InvalidateTags extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $accessCheck = $this->accessManager->checkWriteAccess('invalidate', 'cache');
    if (!$accessCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $accessCheck['reason'],
        'code' => $accessCheck['code'] ?? 'ACCESS_DENIED',
      ];
    }

    $tags = $input['tags'] ?? [];
    if (empty($tags)) {
      return [
        'success' => FALSE,
        'error' => 'At least one cache tag is required.',
      ];
    }

    $result = $this->cacheService->invalidateTags($tags);

    if ($result['success']) {
      $this->auditLogger->log('invalidate', 'cache_tags', implode(',', $tags), [
        'tags' => $tags,
      ]);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'tags' => [
        'type' => 'array',
        'label' => 'Cache Tags',
        'description' => 'Array of cache tags to invalidate (e.g., ["node:1", "node_list"]).',
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
      'tags' => [
        'type' => 'array',
        'label' => 'Invalidated tags',
      ],
    ];
  }

}
