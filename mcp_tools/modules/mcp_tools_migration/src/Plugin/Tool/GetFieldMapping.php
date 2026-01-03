<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_migration\Service\MigrationService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting field mapping information for a content type.
 *
 * @Tool(
 *   id = "mcp_migration_field_mapping",
 *   label = @Translation("Get Field Mapping"),
 *   description = @Translation("Get required and optional fields for a content type to help prepare import data."),
 *   category = "migration",
 * )
 */
class GetFieldMapping extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    if (empty($contentType)) {
      return ['success' => FALSE, 'error' => 'Content type is required.'];
    }

    return $this->migrationService->getFieldMapping($contentType);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'content_type' => [
        'type' => 'string',
        'label' => 'Content Type',
        'description' => 'The machine name of the content type to get field information for.',
        'required' => TRUE,
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
        'description' => 'The content type machine name.',
      ],
      'label' => [
        'type' => 'string',
        'label' => 'Label',
        'description' => 'The human-readable content type label.',
      ],
      'required' => [
        'type' => 'object',
        'label' => 'Required Fields',
        'description' => 'Required fields with label, type, and description.',
      ],
      'optional' => [
        'type' => 'object',
        'label' => 'Optional Fields',
        'description' => 'Optional fields with label, type, and description.',
      ],
    ];
  }

}
