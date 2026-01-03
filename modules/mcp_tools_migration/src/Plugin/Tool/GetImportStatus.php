<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_migration\Service\MigrationService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting the status of the last import operation.
 *
 * @Tool(
 *   id = "mcp_migration_import_status",
 *   label = @Translation("Get Import Status"),
 *   description = @Translation("Get the status of the last import operation."),
 *   category = "migration",
 * )
 */
class GetImportStatus extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return $this->migrationService->getImportStatus();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'has_import' => [
        'type' => 'boolean',
        'label' => 'Has Import',
        'description' => 'Whether there is a recent import record.',
      ],
      'import_id' => [
        'type' => 'string',
        'label' => 'Import ID',
        'description' => 'Unique identifier for the import operation.',
      ],
      'status' => [
        'type' => 'string',
        'label' => 'Status',
        'description' => 'Import status: in_progress, completed, or failed.',
      ],
      'total_items' => [
        'type' => 'integer',
        'label' => 'Total Items',
        'description' => 'Total number of items in the import.',
      ],
      'processed' => [
        'type' => 'integer',
        'label' => 'Processed',
        'description' => 'Number of items processed.',
      ],
      'failed' => [
        'type' => 'integer',
        'label' => 'Failed',
        'description' => 'Number of items that failed.',
      ],
      'started_at' => [
        'type' => 'string',
        'label' => 'Started At',
        'description' => 'When the import started.',
      ],
      'updated_at' => [
        'type' => 'string',
        'label' => 'Updated At',
        'description' => 'When the status was last updated.',
      ],
    ];
  }

}
