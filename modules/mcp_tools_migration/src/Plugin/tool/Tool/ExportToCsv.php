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
  id: 'mcp_migration_export_csv',
  label: new TranslatableMarkup('Export to CSV'),
  description: new TranslatableMarkup('Export content of a type to CSV format. Limited to 100 items.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'content_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('The machine name of the content type to export.'),
      required: TRUE,
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum number of items to export (default: 100, max: 100).'),
      required: FALSE,
    ),
    'offset' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Offset'),
      description: new TranslatableMarkup('How many nodes to skip, in id order. The answer gives next_offset while there are more.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'total' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total'),
      description: new TranslatableMarkup('Nodes of the type the user may view.'),
    ),
    'next_offset' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Next offset'),
      description: new TranslatableMarkup('The offset of the next page; empty when this was the last.'),
    ),
    'content_type' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('The content type that was exported.'),
    ),
    'exported_count' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Exported Count'),
      description: new TranslatableMarkup('Number of items exported.'),
    ),
    'fields' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Fields'),
      description: new TranslatableMarkup('List of field names in the CSV columns.'),
    ),
    'csv_data' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('CSV Data'),
      description: new TranslatableMarkup('The exported data in CSV format.'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('Result message.'),
    ),
  ],
)]
class ExportToCsv extends McpToolsToolBase {

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
    $limit = isset($input['limit']) ? (int) $input['limit'] : 100;
    $offset = (int) ($input['offset'] ?? 0);

    if (empty($contentType)) {
      return ['success' => FALSE, 'error' => 'Content type is required.'];
    }

    return $this->migrationService->exportToCsv($contentType, $limit, $offset);
  }

}
