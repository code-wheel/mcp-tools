<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Plugin\tool\Tool;

use Drupal\mcp_tools_migration\Service\MigrationService;
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
  id: 'mcp_migration_import_csv',
  label: new TranslatableMarkup('Import from CSV'),
  description: new TranslatableMarkup('Import content from CSV data. Limited to 100 items per call.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'content_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('The machine name of the content type to import into.'),
      required: TRUE,
    ),
    'csv_data' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('CSV Data'),
      description: new TranslatableMarkup('CSV string with header row as first line. Use title column for content title.'),
      required: TRUE,
    ),
    'field_mapping' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Field Mapping'),
      description: new TranslatableMarkup('Optional mapping of CSV column names to Drupal field names (e.g., {"Name": "title", "Description": "body"}).'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'import_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Import ID'),
      description: new TranslatableMarkup('Unique identifier for this import operation.'),
    ),
    'content_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('The content type that was imported.'),
    ),
    'total_items' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Items'),
      description: new TranslatableMarkup('Total number of items processed.'),
    ),
    'created_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Created Count'),
      description: new TranslatableMarkup('Number of items successfully created.'),
    ),
    'failed_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Failed Count'),
      description: new TranslatableMarkup('Number of items that failed to import.'),
    ),
    'created' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Created Items'),
      description: new TranslatableMarkup('List of successfully created items with nid and title.'),
    ),
    'failed' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Failed Items'),
      description: new TranslatableMarkup('List of failed items with error details.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('Result message.'),
    ),
  ],
)]
class ImportFromCsv extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'migration';


  protected MigrationService $migrationService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->migrationService = $container->get('mcp_tools_migration.migration');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
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

  

  

}
