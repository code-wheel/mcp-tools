<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_batch\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_batch\Service\BatchService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_batch_update_content",
 *   label = @Translation("Batch Update Content"),
 *   description = @Translation("Update multiple content items (nodes) at once. Maximum 50 items per operation."),
 *   category = "batch",
 * )
 */
class UpdateMultipleContent extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected BatchService $batchService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->batchService = $container->get('mcp_tools_batch.batch');
    return $instance;
  }

  public function execute(array $input = []): array {
    $updates = $input['updates'] ?? [];

    if (empty($updates)) {
      return ['success' => FALSE, 'error' => 'Updates array is required.'];
    }

    return $this->batchService->updateMultipleContent($updates);
  }

  public function getInputDefinition(): array {
    return [
      'updates' => [
        'type' => 'array',
        'label' => 'Updates',
        'description' => 'Array of update objects. Each should have "id" (node ID) and "fields" object with field name/value pairs to update.',
        'required' => TRUE,
      ],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'total_requested' => ['type' => 'integer', 'label' => 'Total Requested'],
      'updated_count' => ['type' => 'integer', 'label' => 'Updated Count'],
      'error_count' => ['type' => 'integer', 'label' => 'Error Count'],
      'updated' => ['type' => 'array', 'label' => 'Updated Items'],
      'errors' => ['type' => 'array', 'label' => 'Errors'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
