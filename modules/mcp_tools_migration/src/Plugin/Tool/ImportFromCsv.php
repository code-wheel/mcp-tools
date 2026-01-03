<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_migration\Service\MigrationService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for importing content from CSV data.
 *
 * Requires AccessManager and AuditLogger for secure import operations.
 *
 * @Tool(
 *   id = "mcp_migration_import_csv",
 *   label = @Translation("Import from CSV"),
 *   description = @Translation("Import content from CSV data. Limited to 100 items per call."),
 *   category = "migration",
 * )
 */
class ImportFromCsv extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $csvData = $input['csv_data'] ?? '';
    $fieldMapping = $input['field_mapping'] ?? [];

    if (empty($contentType)) {
      return ['success' => FALSE, 'error' => 'Content type is required.'];
    }

    if (empty($csvData)) {
      return ['success' => FALSE, 'error' => 'CSV data is required.'];
    }

    return $this->migrationService->importFromCsv($contentType, $csvData, $fieldMapping);
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
      'csv_data' => [
        'type' => 'string',
        'label' => 'CSV Data',
        'description' => 'CSV string with header row as first line. Use title column for content title.',
        'required' => TRUE,
      ],
      'field_mapping' => [
        'type' => 'object',
        'label' => 'Field Mapping',
        'description' => 'Optional mapping of CSV column names to Drupal field names (e.g., {"Name": "title", "Description": "body"}).',
        'required' => FALSE,
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
