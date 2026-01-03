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
  id: 'mcp_cache_clear_entity',
  label: new TranslatableMarkup('Clear Entity Cache'),
  description: new TranslatableMarkup('Clear render cache for a specific entity.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type (e.g., node, user, taxonomy_term).'),
      required: TRUE,
    ),
    'entity_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The entity ID.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'success' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Success status'),
      description: new TranslatableMarkup(''),
    ),
    'invalidated_tags' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Invalidated cache tags'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class ClearEntityCache extends McpToolsToolBase {

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

  

  

}
