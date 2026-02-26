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
  id: 'mcp_batch_update_content',
  label: new TranslatableMarkup('Batch Update Content'),
  description: new TranslatableMarkup('Update multiple content items (nodes) at once. Maximum 50 items per operation.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'updates' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Updates'),
      description: new TranslatableMarkup('Array of update objects. Each should have "id" (node ID) and "fields" object with field name/value pairs to update.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'total_requested' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Requested'),
      description: new TranslatableMarkup('Number of items requested for update.'),
    ),
    'updated_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Updated Count'),
      description: new TranslatableMarkup('Number of items successfully updated.'),
    ),
    'error_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Error Count'),
      description: new TranslatableMarkup('Number of items that failed to update.'),
    ),
    'updated' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Updated Items'),
      description: new TranslatableMarkup('Array of updated items with nid, title, and changed_fields list.'),
    ),
    'errors' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Errors'),
      description: new TranslatableMarkup('Array of errors with nid and error message for each failure.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Summary of the batch update operation.'),
    ),
  ],
)]
class UpdateMultipleContent extends McpToolsToolBase {

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
    $updates = $input['updates'] ?? [];

    if (empty($updates)) {
      return ['success' => FALSE, 'error' => 'Updates array is required.'];
    }

    return $this->batchService->updateMultipleContent($updates);
  }

}
