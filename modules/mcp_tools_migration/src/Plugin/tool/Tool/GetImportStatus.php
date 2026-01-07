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
  id: 'mcp_migration_import_status',
  label: new TranslatableMarkup('Get Import Status'),
  description: new TranslatableMarkup('Get the status of the last import operation.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'has_import' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Has Import'),
      description: new TranslatableMarkup('Whether there is a recent import record.'),
    ),
    'import_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Import ID'),
      description: new TranslatableMarkup('Unique identifier for the import operation.'),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup('Import status: in_progress, completed, or failed.'),
    ),
    'total_items' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Items'),
      description: new TranslatableMarkup('Total number of items in the import.'),
    ),
    'processed' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Processed'),
      description: new TranslatableMarkup('Number of items processed.'),
    ),
    'failed' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Failed'),
      description: new TranslatableMarkup('Number of items that failed.'),
    ),
    'started_at' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Started At'),
      description: new TranslatableMarkup('When the import started.'),
    ),
    'updated_at' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Updated At'),
      description: new TranslatableMarkup('When the status was last updated.'),
    ),
  ],
)]
class GetImportStatus extends McpToolsToolBase {

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
    return $this->migrationService->getImportStatus();
  }

  

  

}
