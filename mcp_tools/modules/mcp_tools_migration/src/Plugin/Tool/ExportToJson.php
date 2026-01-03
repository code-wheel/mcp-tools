<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_migration\Service\MigrationService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for exporting content to JSON format.
 *
 * @Tool(
 *   id = "mcp_migration_export_json",
 *   label = @Translation("Export to JSON"),
 *   description = @Translation("Export content of a type to JSON format. Limited to 100 items."),
 *   category = "migration",
 * )
 */
class ExportToJson extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    return $this->migrationService->exportToJson($contentType, $limit);
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
      'items' => [
        'type' => 'array',
        'label' => 'Items',
        'description' => 'Array of exported content items.',
      ],
      'message' => [
        'type' => 'string',
        'label' => 'Message',
        'description' => 'Result message.',
      ],
    ];
  }

}
