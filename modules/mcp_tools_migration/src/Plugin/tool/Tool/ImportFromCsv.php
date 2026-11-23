<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Plugin\tool\Tool;

use Drupal\mcp_tools_migration\Service\MigrationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
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
  description: new TranslatableMarkup('Import content, terms or redirects from CSV data, the first line the column names. Rows run as a Drupal migration with a key: a second import with the same key updates instead of duplicating, and 100 rows run a call (call again while done is false). A field the type does not have is refused. Reference fields take ids or names, several with |. mcp_migration_rollback undoes an import.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'content_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('The content type, or for terms the vocabulary. Left out for redirects.'),
      required: FALSE,
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
    'key' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Key'),
      description: new TranslatableMarkup('The column whose value names a row for good: an id from the source, or "uuid" (an export gives it). Required for content; terms are keyed by name and redirects by source when left out. A later import with the same key updates what this one made instead of making copies.'),
      required: FALSE,
    ),
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity type'),
      description: new TranslatableMarkup('node (the default), taxonomy_term (content_type is the vocabulary; a "parent" column may name another row) or redirect (columns source, destination, status_code; content_type left out).'),
      required: FALSE,
    ),
    'dry_run' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Dry run'),
      description: new TranslatableMarkup('TRUE: say for each row whether it would be created or updated, and which rows are refused, and write nothing.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'import_id' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Import ID'),
      description: new TranslatableMarkup('Unique identifier for this import operation.'),
    ),
    'content_type' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('The content type that was imported.'),
    ),
    'total_items' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Items'),
      description: new TranslatableMarkup('Total number of items processed.'),
    ),
    'created_count' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Created Count'),
      description: new TranslatableMarkup('Number of items successfully created.'),
    ),
    'failed_count' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Failed Count'),
      description: new TranslatableMarkup('Number of items that failed to import.'),
    ),
    'created' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Created Items'),
      description: new TranslatableMarkup('List of successfully created items with nid and title.'),
    ),
    'updated_count' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Updated'),
      description: new TranslatableMarkup('Rows that updated a node an earlier import with this key made.'),
    ),
    'remaining' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Remaining'),
      description: new TranslatableMarkup('Rows not run yet. Call again with the same rows and key to go on.'),
    ),
    'done' => new OutputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Done'),
      description: new TranslatableMarkup('Whether every row has run.'),
    ),
    'plan' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Plan'),
      description: new TranslatableMarkup('With dry_run: each row with its key and its action, create or update.'),
    ),
    'existing' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('There already'),
      description: new TranslatableMarkup('Rows whose term or redirect was there and not made by this import: left as they are, with their id.'),
    ),
    'warnings' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Warnings'),
      description: new TranslatableMarkup('Redirects made to a destination that leads nowhere.'),
    ),
    'ignored_columns' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Ignored columns'),
      description: new TranslatableMarkup('Columns that are never imported: ids, the owner, the dates.'),
    ),
    'failed' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Failed Items'),
      description: new TranslatableMarkup('List of failed items with error details.'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('Result message.'),
    ),
  ],
)]
class ImportFromCsv extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'migration';


  /**
   * The migration service.
   *
   * @var \Drupal\mcp_tools_migration\Service\MigrationService
   */
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

    $entityType = (string) ($input['entity_type'] ?? 'node');
    if (empty($contentType) && $entityType !== 'redirect') {
      return ['success' => FALSE, 'error' => 'Content type is required.'];
    }

    if (empty($csvData)) {
      return ['success' => FALSE, 'error' => 'CSV data is required.'];
    }

    $options = ['dry_run' => !empty($input['dry_run']), 'entity_type' => $entityType];
    return $this->migrationService->importFromCsv($contentType, $csvData, (array) $fieldMapping, (string) ($input['key'] ?? ''), $options);
  }

}
