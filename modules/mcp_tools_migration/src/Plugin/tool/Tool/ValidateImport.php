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
  id: 'mcp_migration_validate',
  label: new TranslatableMarkup('Validate Import'),
  description: new TranslatableMarkup("Deprecated, and removed in a later release: use mcp_migration_import_json with dry_run, which runs the import's own checks (the key, references by id or name, a term's parent) and writes nothing. This older check knows fewer of them."),
  operation: ToolOperation::Read,
  input_definitions: [
    'content_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('The machine name of the content type to validate against.'),
      required: TRUE,
    ),
    'items' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Items'),
      description: new TranslatableMarkup('Array of items to validate. Each item should have a "title" key and field values.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'deprecated' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Deprecated'),
      description: new TranslatableMarkup('What to use instead.'),
    ),
    'valid' => new OutputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Valid'),
      description: new TranslatableMarkup('Whether the data is valid for import.'),
    ),
    'total_items' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Items'),
      description: new TranslatableMarkup('Total number of items validated.'),
    ),
    'error_count' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Error Count'),
      description: new TranslatableMarkup('Number of validation errors found.'),
    ),
    'warning_count' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Warning Count'),
      description: new TranslatableMarkup('Number of validation warnings found.'),
    ),
    'errors' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Errors'),
      description: new TranslatableMarkup('List of validation errors with row number, field, and message.'),
    ),
    'warnings' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Warnings'),
      description: new TranslatableMarkup('List of validation warnings with row number, field, and message.'),
    ),
  ],
)]
class ValidateImport extends McpToolsToolBase {

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

    $result = $this->migrationService->validateImport($contentType, $items);
    // Said in the answer too: a model that called it reads this, not the
    // tool list.
    if (!empty($result['success'])) {
      $result['data']['deprecated'] = 'mcp_migration_validate is deprecated: use mcp_migration_import_json with dry_run, which runs the checks the import itself runs and writes nothing.';
    }
    return $result;
  }

}
