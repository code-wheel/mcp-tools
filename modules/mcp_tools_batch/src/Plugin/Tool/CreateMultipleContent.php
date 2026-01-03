<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_batch\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_batch\Service\BatchService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_batch_create_content",
 *   label = @Translation("Batch Create Content"),
 *   description = @Translation("Create multiple content items (nodes) at once. Maximum 50 items per operation."),
 *   category = "batch",
 * )
 */
class CreateMultipleContent extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected BatchService $batchService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->batchService = $container->get('mcp_tools_batch.batch');
    return $instance;
  }

  public function execute(array $input = []): array {
    $contentType = $input['content_type'] ?? '';
    $items = $input['items'] ?? [];

    if (empty($contentType)) {
      return ['success' => FALSE, 'error' => 'Content type is required.'];
    }

    if (empty($items)) {
      return ['success' => FALSE, 'error' => 'Items array is required.'];
    }

    return $this->batchService->createMultipleContent($contentType, $items);
  }

  public function getInputDefinition(): array {
    return [
      'content_type' => [
        'type' => 'string',
        'label' => 'Content Type',
        'description' => 'The machine name of the content type (e.g., "article", "page").',
        'required' => TRUE,
      ],
      'items' => [
        'type' => 'array',
        'label' => 'Items',
        'description' => 'Array of content items to create. Each item should have "title" and optionally "fields" object, "status" boolean.',
        'required' => TRUE,
      ],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'content_type' => ['type' => 'string', 'label' => 'Content Type'],
      'total_requested' => ['type' => 'integer', 'label' => 'Total Requested'],
      'created_count' => ['type' => 'integer', 'label' => 'Created Count'],
      'error_count' => ['type' => 'integer', 'label' => 'Error Count'],
      'created' => ['type' => 'array', 'label' => 'Created Items'],
      'errors' => ['type' => 'array', 'label' => 'Errors'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
