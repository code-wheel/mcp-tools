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
  id: 'mcp_migration_export_json',
  label: new TranslatableMarkup('Export to JSON'),
  description: new TranslatableMarkup('Export content of a type to JSON format. Limited to 100 items.'),
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
  ],
  output_definitions: [
    'content_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('The content type that was exported.'),
    ),
    'exported_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Exported Count'),
      description: new TranslatableMarkup('Number of items exported.'),
    ),
    'items' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Items'),
      description: new TranslatableMarkup('Array of exported content items.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('Result message.'),
    ),
  ],
)]
class ExportToJson extends McpToolsToolBase {

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

    if (empty($contentType)) {
      return ['success' => FALSE, 'error' => 'Content type is required.'];
    }

    return $this->migrationService->exportToJson($contentType, $limit);
  }

}
