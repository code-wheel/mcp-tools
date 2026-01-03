<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_migration\Service\MigrationService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for exporting content to CSV format.
 *
 * @Tool(
 *   id = "mcp_migration_export_csv",
 *   label = @Translation("Export to CSV"),
 *   description = @Translation("Export content of a type to CSV format. Limited to 100 items."),
 *   category = "migration",
 * )
 */
class ExportToCsv extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $limit = isset($input['limit']) ? (int) $input['limit'] : 100;

    if (empty($contentType)) {
      return ['success' => FALSE, 'error' => 'Content type is required.'];
    }

    return $this->migrationService->exportToCsv($contentType, $limit);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'content_type' => [
        'type' => 'string',
        'label' => 'Content Type',
        'description' => 'The machine name of the content type to export.',
        'required' => TRUE,
      ],
      'limit' => [
        'type' => 'integer',
        'label' => 'Limit',
        'description' => 'Maximum number of items to export (default: 100, max: 100).',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'content_type' => [
        'type' => 'string',
        'label' => 'Content Type',
        'description' => 'The content type that was exported.',
      ],
      'exported_count' => [
        'type' => 'integer',
        'label' => 'Exported Count',
        'description' => 'Number of items exported.',
      ],
      'fields' => [
        'type' => 'array',
        'label' => 'Fields',
        'description' => 'List of field names in the CSV columns.',
      ],
      'csv_data' => [
        'type' => 'string',
        'label' => 'CSV Data',
        'description' => 'The exported data in CSV format.',
      ],
      'message' => [
        'type' => 'string',
        'label' => 'Message',
        'description' => 'Result message.',
      ],
    ];
  }

}
