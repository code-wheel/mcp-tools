<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_batch\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_batch\Service\BatchService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_batch_delete_content",
 *   label = @Translation("Batch Delete Content"),
 *   description = @Translation("Delete multiple content items (nodes) at once. Maximum 50 items per operation."),
 *   category = "batch",
 * )
 */
class DeleteMultipleContent extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected BatchService $batchService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->batchService = $container->get('mcp_tools_batch.batch');
    return $instance;
  }

  public function execute(array $input = []): array {
    $ids = $input['ids'] ?? [];
    $force = (bool) ($input['force'] ?? FALSE);

    if (empty($ids)) {
      return ['success' => FALSE, 'error' => 'Node IDs array is required.'];
    }

    return $this->batchService->deleteMultipleContent($ids, $force);
  }

  public function getInputDefinition(): array {
    return [
      'ids' => [
        'type' => 'array',
        'label' => 'Node IDs',
        'description' => 'Array of node IDs to delete.',
        'required' => TRUE,
      ],
      'force' => [
        'type' => 'boolean',
        'label' => 'Force Delete',
        'description' => 'If true, delete even published content. Default is false (only unpublished content will be deleted).',
        'required' => FALSE,
      ],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'total_requested' => ['type' => 'integer', 'label' => 'Total Requested'],
      'deleted_count' => ['type' => 'integer', 'label' => 'Deleted Count'],
      'skipped_count' => ['type' => 'integer', 'label' => 'Skipped Count'],
      'error_count' => ['type' => 'integer', 'label' => 'Error Count'],
      'deleted' => ['type' => 'array', 'label' => 'Deleted Items'],
      'skipped' => ['type' => 'array', 'label' => 'Skipped Items'],
      'errors' => ['type' => 'array', 'label' => 'Errors'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
