<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_config\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_config\Service\ConfigManagementService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for previewing what an operation would do (dry-run mode).
 *
 * @Tool(
 *   id = "mcp_config_preview",
 *   label = @Translation("Preview Operation"),
 *   description = @Translation("Dry-run mode: preview what an operation would do without executing it."),
 *   category = "config",
 * )
 */
class PreviewOperation extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ConfigManagementService $configManagement;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->configManagement = $container->get('mcp_tools_config.config_management');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $operation = $input['operation'] ?? '';

    if (empty($operation)) {
      return [
        'success' => FALSE,
        'error' => 'operation is required.',
        'supported_operations' => [
          'export_config' => 'Preview what would be exported to sync directory',
          'import_config' => 'Preview what would be imported from sync directory',
          'delete_config' => 'Preview deleting a configuration (requires config_name param)',
          'create_content_type' => 'Preview creating a content type (requires machine_name param)',
          'add_field' => 'Preview adding a field (requires bundle, field_name params)',
          'create_vocabulary' => 'Preview creating a vocabulary (requires machine_name param)',
          'create_view' => 'Preview creating a view (requires id param)',
        ],
      ];
    }

    $params = $input['params'] ?? [];

    return $this->configManagement->previewOperation($operation, $params);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'operation' => [
        'type' => 'string',
        'label' => 'Operation',
        'description' => 'The operation to preview: export_config, import_config, delete_config, create_content_type, add_field, create_vocabulary, create_view.',
        'required' => TRUE,
      ],
      'params' => [
        'type' => 'object',
        'label' => 'Parameters',
        'description' => 'Parameters for the operation (varies by operation type).',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'dry_run' => [
        'type' => 'boolean',
        'label' => 'Dry Run',
        'description' => 'Always true, indicating this is a preview.',
      ],
      'operation' => [
        'type' => 'string',
        'label' => 'Operation',
        'description' => 'The operation that was previewed.',
      ],
      'action' => [
        'type' => 'string',
        'label' => 'Action',
        'description' => 'Human-readable description of the action.',
      ],
      'description' => [
        'type' => 'string',
        'label' => 'Description',
        'description' => 'Detailed description of what would happen.',
      ],
      'affected_configs' => [
        'type' => 'map',
        'label' => 'Affected Configs',
        'description' => 'Configuration objects that would be affected.',
      ],
      'note' => [
        'type' => 'string',
        'label' => 'Note',
        'description' => 'Reminder that this is a preview.',
      ],
    ];
  }

}
