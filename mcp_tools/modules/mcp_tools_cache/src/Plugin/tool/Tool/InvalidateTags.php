<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_cache\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
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
  id: 'mcp_cache_invalidate_tags',
  label: new TranslatableMarkup('Invalidate Cache Tags'),
  description: new TranslatableMarkup('Invalidate specific cache tags to clear related cached data.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'tags' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Cache Tags'),
      description: new TranslatableMarkup('Array of cache tags to invalidate (e.g., ["node:1", "node_list"]).'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'success' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Success status'),
      description: new TranslatableMarkup('True if tags were invalidated successfully.'),
    ),
    'tags' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Invalidated tags'),
      description: new TranslatableMarkup('List of cache tags that were invalidated.'),
    ),
  ],
)]
class InvalidateTags extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'cache';


  protected CacheService $cacheService;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->cacheService = $container->get('mcp_tools_cache.cache_service');
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    $instance->auditLogger = $container->get('mcp_tools.audit_logger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
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

  

  

}
