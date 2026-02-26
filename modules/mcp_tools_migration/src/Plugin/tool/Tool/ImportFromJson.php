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
  id: 'mcp_migration_import_json',
  label: new TranslatableMarkup('Import from JSON'),
  description: new TranslatableMarkup('Import content from JSON array. Limited to 100 items per call.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'content_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('The machine name of the content type to import into.'),
      required: TRUE,
    ),
    'items' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Items'),
      description: new TranslatableMarkup('Array of items to import. Each item should have a "title" key and field values.'),
      required: TRUE,
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
class ImportFromJson extends McpToolsToolBase {

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
    $items = $input['items'] ?? [];

    if (empty($contentType)) {
      return ['success' => FALSE, 'error' => 'Content type is required.'];
    }

    if (empty($items) || !is_array($items)) {
      return ['success' => FALSE, 'error' => 'Items array is required and must contain at least one item.'];
    }

    return $this->migrationService->importFromJson($contentType, $items);
  }

}
