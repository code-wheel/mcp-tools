<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_batch\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_batch\Service\BatchService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_batch_publish",
 *   label = @Translation("Batch Publish/Unpublish Content"),
 *   description = @Translation("Publish or unpublish multiple content items at once. Maximum 50 items per operation."),
 *   category = "batch",
 * )
 */
class PublishMultiple extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected BatchService $batchService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->batchService = $container->get('mcp_tools_batch.batch');
    return $instance;
  }

  public function execute(array $input = []): array {
    $ids = $input['ids'] ?? [];
    $publish = $input['publish'] ?? TRUE;

    if (empty($ids)) {
      return ['success' => FALSE, 'error' => 'Node IDs array is required.'];
    }

    return $this->batchService->publishMultiple($ids, (bool) $publish);
  }

  public function getInputDefinition(): array {
    return [
      'ids' => [
        'type' => 'array',
        'label' => 'Node IDs',
        'description' => 'Array of node IDs to publish or unpublish.',
        'required' => TRUE,
      ],
      'publish' => [
        'type' => 'boolean',
        'label' => 'Publish',
        'description' => 'If true, publish the content. If false, unpublish. Default is true.',
        'required' => FALSE,
      ],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'action' => ['type' => 'string', 'label' => 'Action Performed'],
      'total_requested' => ['type' => 'integer', 'label' => 'Total Requested'],
      'processed_count' => ['type' => 'integer', 'label' => 'Processed Count'],
      'unchanged_count' => ['type' => 'integer', 'label' => 'Unchanged Count'],
      'error_count' => ['type' => 'integer', 'label' => 'Error Count'],
      'processed' => ['type' => 'array', 'label' => 'Processed Items'],
      'unchanged' => ['type' => 'array', 'label' => 'Unchanged Items'],
      'errors' => ['type' => 'array', 'label' => 'Errors'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
