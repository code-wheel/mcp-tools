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
  id: 'mcp_cache_clear_bin',
  label: new TranslatableMarkup('Clear Cache Bin'),
  description: new TranslatableMarkup('Clear a specific cache bin (e.g., render, page, entity).'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'bin' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Cache Bin'),
      description: new TranslatableMarkup('The cache bin to clear (e.g., render, page, entity, menu).'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'success' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Success status'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class ClearCacheBin extends McpToolsToolBase {

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

  

  

}
