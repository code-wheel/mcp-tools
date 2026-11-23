<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Plugin\tool\Tool;

use Drupal\mcp_tools_migration\Service\MigrationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\OutputDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_migration_import_status',
  label: new TranslatableMarkup('Get Import Status'),
  description: new TranslatableMarkup('What an import made and what failed, from its migration record. Without import_id it lists every import.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'import_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Import ID'),
      description: new TranslatableMarkup('The import_id an import answered with. Leave out to list the imports.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'has_import' => new OutputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Has Import'),
      description: new TranslatableMarkup('Whether there is an import.'),
    ),
    'imports' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Imports'),
      description: new TranslatableMarkup('Without import_id: each import with its counts.'),
    ),
    'import_id' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Import ID'),
      description: new TranslatableMarkup('The import.'),
    ),
    'status' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup('Idle, or Importing while a run is going on.'),
    ),
    'total_rows' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total rows'),
      description: new TranslatableMarkup('Rows in the import.'),
    ),
    'imported' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Imported'),
      description: new TranslatableMarkup('Rows that made or updated a node.'),
    ),
    'failed' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Failed'),
      description: new TranslatableMarkup('Rows that failed.'),
    ),
    'processed' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Processed'),
      description: new TranslatableMarkup('Rows that have run.'),
    ),
    'messages' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Messages'),
      description: new TranslatableMarkup('Why rows failed, by key (up to 20).'),
    ),
  ],
)]
class GetImportStatus extends McpToolsToolBase {

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
    $importId = (string) ($input['import_id'] ?? '');
    return $this->migrationService->getImportStatus($importId === '' ? NULL : $importId);
  }

}
