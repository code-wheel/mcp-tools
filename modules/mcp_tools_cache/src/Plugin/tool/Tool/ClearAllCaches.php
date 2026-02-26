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

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_cache_clear_all',
  label: new TranslatableMarkup('Clear All Caches'),
  description: new TranslatableMarkup('Clear all Drupal caches (equivalent to drush cr).'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [],
  output_definitions: [
    'success' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Success status'),
      description: new TranslatableMarkup('True if all caches cleared successfully.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result message'),
      description: new TranslatableMarkup('Confirmation message. Site may be slow briefly while caches rebuild.'),
    ),
  ],
)]
class ClearAllCaches extends McpToolsToolBase {

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

}
