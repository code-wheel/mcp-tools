<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_search_api\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_search_api\Service\SearchApiService;
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
  id: 'mcp_search_api_index',
  label: new TranslatableMarkup('Index Items'),
  description: new TranslatableMarkup('Index a batch of items on a search index.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Index ID'),
      description: new TranslatableMarkup('The machine name of the search index.'),
      required: TRUE,
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum number of items to index (default: 100).'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'success' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Success status'),
      description: new TranslatableMarkup('TRUE if indexing completed successfully, FALSE if an error occurred.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result message'),
      description: new TranslatableMarkup('Human-readable summary of the indexing operation.'),
    ),
    'items_indexed' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Number of items indexed'),
      description: new TranslatableMarkup('Count of items processed in this batch. May be less than limit if fewer items pending.'),
    ),
    'remaining' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Remaining items to index'),
      description: new TranslatableMarkup('Number of items still pending indexing. Call IndexItems again to continue. Zero when complete.'),
    ),
  ],
)]
class IndexItems extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'search_api';


  /**
   * The search api service.
   *
   * @var \Drupal\mcp_tools_search_api\Service\SearchApiService
   */
  protected SearchApiService $searchApiService;
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
    $instance->searchApiService = $container->get('mcp_tools_search_api.search_api_service');
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    $instance->auditLogger = $container->get('mcp_tools.audit_logger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    // Check write access.
    $accessCheck = $this->accessManager->checkWriteAccess('index', 'search_api_index');
    if (!$accessCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $accessCheck['reason'],
        'code' => $accessCheck['code'] ?? 'ACCESS_DENIED',
      ];
    }

    $id = $input['id'] ?? '';
    if (empty($id)) {
      return [
        'success' => FALSE,
        'error' => 'id is required.',
        'code' => 'VALIDATION_ERROR',
      ];
    }

    $limit = (int) ($input['limit'] ?? 100);
    if ($limit <= 0) {
      $limit = 100;
    }

    $result = $this->searchApiService->indexItems($id, $limit);

    if ($result['success']) {
      $this->auditLogger->log('index', 'search_api_index', $id, [
        'items_indexed' => $result['items_indexed'] ?? 0,
        'remaining' => $result['remaining'] ?? 0,
      ]);
    }

    return $result;
  }

}
