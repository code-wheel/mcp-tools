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
  id: 'mcp_batch_publish',
  label: new TranslatableMarkup('Batch Publish/Unpublish Content'),
  description: new TranslatableMarkup('Publish or unpublish multiple content items at once. Maximum 50 items per operation.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'ids' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Node IDs'),
      description: new TranslatableMarkup('Array of node IDs to publish or unpublish.'),
      required: TRUE,
    ),
    'publish' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Publish'),
      description: new TranslatableMarkup('If true, publish the content. If false, unpublish. Default is true.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'action' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Action Performed'),
      description: new TranslatableMarkup('Either "published" or "unpublished" depending on the publish parameter.'),
    ),
    'total_requested' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Requested'),
      description: new TranslatableMarkup('Number of items requested for status change.'),
    ),
    'processed_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Processed Count'),
      description: new TranslatableMarkup('Number of items whose status was actually changed.'),
    ),
    'unchanged_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Unchanged Count'),
      description: new TranslatableMarkup('Number of items already in the target state.'),
    ),
    'error_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Error Count'),
      description: new TranslatableMarkup('Number of items that failed to process.'),
    ),
    'processed' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Processed Items'),
      description: new TranslatableMarkup('Array of items with nid, title whose status was changed.'),
    ),
    'unchanged' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Unchanged Items'),
      description: new TranslatableMarkup('Array of items already in the target publish state.'),
    ),
    'errors' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Errors'),
      description: new TranslatableMarkup('Array of errors with nid and error message for each failure.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Summary of the batch publish operation.'),
    ),
  ],
)]
class PublishMultiple extends McpToolsToolBase {

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
    $publish = $input['publish'] ?? TRUE;

    if (empty($ids)) {
      return ['success' => FALSE, 'error' => 'Node IDs array is required.'];
    }

    return $this->batchService->publishMultiple($ids, (bool) $publish);
  }

}
