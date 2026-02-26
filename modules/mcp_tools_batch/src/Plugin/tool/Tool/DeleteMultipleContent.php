<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_batch\Plugin\tool\Tool;

use Drupal\mcp_tools_batch\Service\BatchService;
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
  id: 'mcp_batch_delete_content',
  label: new TranslatableMarkup('Batch Delete Content'),
  description: new TranslatableMarkup('Delete multiple content items (nodes) at once. Maximum 50 items per operation.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'ids' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Node IDs'),
      description: new TranslatableMarkup('Array of node IDs to delete.'),
      required: TRUE,
    ),
    'force' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Force Delete'),
      description: new TranslatableMarkup('If true, delete even published content. Default is false (only unpublished content will be deleted).'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'total_requested' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Requested'),
      description: new TranslatableMarkup('Number of items requested for deletion.'),
    ),
    'deleted_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Deleted Count'),
      description: new TranslatableMarkup('Number of items permanently deleted.'),
    ),
    'skipped_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Skipped Count'),
      description: new TranslatableMarkup('Number of published items skipped (use force=true to delete these).'),
    ),
    'error_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Error Count'),
      description: new TranslatableMarkup('Number of items that failed to delete.'),
    ),
    'deleted' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Deleted Items'),
      description: new TranslatableMarkup('Array of deleted items with nid and title.'),
    ),
    'skipped' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Skipped Items'),
      description: new TranslatableMarkup('Array of skipped published items. Use force=true to include.'),
    ),
    'errors' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Errors'),
      description: new TranslatableMarkup('Array of errors with nid and error message for each failure.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Summary of the batch delete operation.'),
    ),
  ],
)]
class DeleteMultipleContent extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'batch';


  /**
   * The batch service.
   *
   * @var \Drupal\mcp_tools_batch\Service\BatchService
   */
  protected BatchService $batchService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->batchService = $container->get('mcp_tools_batch.batch');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $ids = $input['ids'] ?? [];
    $force = (bool) ($input['force'] ?? FALSE);

    if (empty($ids)) {
      return ['success' => FALSE, 'error' => 'Node IDs array is required.'];
    }

    return $this->batchService->deleteMultipleContent($ids, $force);
  }

}
