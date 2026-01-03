<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_migration\Service\MigrationService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for validating import data before actual import.
 *
 * @Tool(
 *   id = "mcp_migration_validate",
 *   label = @Translation("Validate Import"),
 *   description = @Translation("Validate data before import to check for errors and missing fields."),
 *   category = "migration",
 * )
 */
class ValidateImport extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $items = $input['items'] ?? [];

    if (empty($contentType)) {
      return ['success' => FALSE, 'error' => 'Content type is required.'];
    }

    if (empty($items) || !is_array($items)) {
      return ['success' => FALSE, 'error' => 'Items array is required and must contain at least one item.'];
    }

    return $this->migrationService->validateImport($contentType, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'content_type' => [
        'type' => 'string',
        'label' => 'Content Type',
        'description' => 'The machine name of the content type to validate against.',
        'required' => TRUE,
      ],
      'items' => [
        'type' => 'array',
        'label' => 'Items',
        'description' => 'Array of items to validate. Each item should have a "title" key and field values.',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'valid' => [
        'type' => 'boolean',
        'label' => 'Valid',
        'description' => 'Whether the data is valid for import.',
      ],
      'total_items' => [
        'type' => 'integer',
        'label' => 'Total Items',
        'description' => 'Total number of items validated.',
      ],
      'error_count' => [
        'type' => 'integer',
        'label' => 'Error Count',
        'description' => 'Number of validation errors found.',
      ],
      'warning_count' => [
        'type' => 'integer',
        'label' => 'Warning Count',
        'description' => 'Number of validation warnings found.',
      ],
      'errors' => [
        'type' => 'array',
        'label' => 'Errors',
        'description' => 'List of validation errors with row number, field, and message.',
      ],
      'warnings' => [
        'type' => 'array',
        'label' => 'Warnings',
        'description' => 'List of validation warnings with row number, field, and message.',
      ],
    ];
  }

}
