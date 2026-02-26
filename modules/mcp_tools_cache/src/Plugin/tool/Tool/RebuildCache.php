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
  id: 'mcp_cache_rebuild',
  label: new TranslatableMarkup('Rebuild Cache'),
  description: new TranslatableMarkup('Rebuild a specific cache type (router, theme, container, menu).'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Rebuild Type'),
      description: new TranslatableMarkup('What to rebuild: router, theme, container, or menu.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'success' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Success status'),
      description: new TranslatableMarkup('True if rebuild completed successfully.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result message'),
      description: new TranslatableMarkup('Confirmation of what was rebuilt.'),
    ),
  ],
)]
class RebuildCache extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'cache';


  /**
   * The cache service.
   *
   * @var \Drupal\mcp_tools_cache\Service\CacheService
   */
  protected CacheService $cacheService;
  /**
   * The access manager.
   *
   * @var \Drupal\mcp_tools\Service\AccessManager
   */
  protected AccessManager $accessManager;
  /**
   * The audit logger.
   *
   * @var \Drupal\mcp_tools\Service\AuditLogger
   */
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

}
