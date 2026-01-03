<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_migration\Service\MigrationService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for importing content from JSON data.
 *
 * Requires AccessManager and AuditLogger for secure import operations.
 *
 * @Tool(
 *   id = "mcp_migration_import_json",
 *   label = @Translation("Import from JSON"),
 *   description = @Translation("Import content from JSON array. Limited to 100 items per call."),
 *   category = "migration",
 * )
 */
class ImportFromJson extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected MigrationService $migrationService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->migrationService = $container->get('mcp_tools_migration.migration');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $contentType = $input['content_type'] ?? '';
    $items = $input['items'] ?? [];

    if (empty($contentType)) {
      return ['success' => FALSE, 'error' => 'Content type is required.'];
    }

    if (empty($items) || !is_array($items)) {
      return ['success' => FALSE, 'error' => 'Items array is required and must contain at least one item.'];
    }

    return $this->migrationService->importFromJson($contentType, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'content_type' => [
        'type' => 'string',
        'label' => 'Content Type',
        'description' => 'The machine name of the content type to import into.',
        'required' => TRUE,
      ],
      'items' => [
        'type' => 'array',
        'label' => 'Items',
        'description' => 'Array of items to import. Each item should have a "title" key and field values.',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'import_id' => [
        'type' => 'string',
        'label' => 'Import ID',
        'description' => 'Unique identifier for this import operation.',
      ],
      'content_type' => [
        'type' => 'string',
        'label' => 'Content Type',
        'description' => 'The content type that was imported.',
      ],
      'total_items' => [
        'type' => 'integer',
        'label' => 'Total Items',
        'description' => 'Total number of items processed.',
      ],
      'created_count' => [
        'type' => 'integer',
        'label' => 'Created Count',
        'description' => 'Number of items successfully created.',
      ],
      'failed_count' => [
        'type' => 'integer',
        'label' => 'Failed Count',
        'description' => 'Number of items that failed to import.',
      ],
      'created' => [
        'type' => 'array',
        'label' => 'Created Items',
        'description' => 'List of successfully created items with nid and title.',
      ],
      'failed' => [
        'type' => 'array',
        'label' => 'Failed Items',
        'description' => 'List of failed items with error details.',
      ],
      'message' => [
        'type' => 'string',
        'label' => 'Message',
        'description' => 'Result message.',
      ],
    ];
  }

}
