<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_config\Plugin\tool\Tool;

use Drupal\mcp_tools_config\Service\ConfigManagementService;
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
  id: 'mcp_config_preview',
  label: new TranslatableMarkup('Preview Operation'),
  description: new TranslatableMarkup('Dry-run mode: preview what an operation would do without executing it.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'operation' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Operation'),
      description: new TranslatableMarkup('The operation to preview (example: export_config, create_content_type, delete_content_type, add_field, delete_field, create_role, grant_permissions).'),
      required: TRUE,
    ),
    'params' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Parameters'),
      description: new TranslatableMarkup('Parameters for the operation (varies by operation type).'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'dry_run' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Dry Run'),
      description: new TranslatableMarkup('Always true, indicating this is a preview.'),
    ),
    'operation' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Operation'),
      description: new TranslatableMarkup('The operation that was previewed.'),
    ),
    'action' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Action'),
      description: new TranslatableMarkup('Human-readable description of the action.'),
    ),
    'description' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup('Detailed description of what would happen.'),
    ),
    'affected_configs' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Affected Configs'),
      description: new TranslatableMarkup('Configuration objects that would be affected.'),
    ),
    'note' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Note'),
      description: new TranslatableMarkup('Reminder that this is a preview.'),
    ),
  ],
)]
class PreviewOperation extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'config';


  protected ConfigManagementService $configManagement;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configManagement = $container->get('mcp_tools_config.config_management');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $operation = $input['operation'] ?? '';

    if (empty($operation)) {
      return [
        'success' => FALSE,
        'error' => 'operation is required.',
        'supported_operations' => [
          'export_config' => 'Preview what would be exported to sync directory',
          'import_config' => 'Preview what would be imported from sync directory',
          'delete_config' => 'Preview deleting a configuration (requires config_name param)',
          'create_role' => 'Preview creating a role (requires id param)',
          'delete_role' => 'Preview deleting a role (requires id param)',
          'grant_permissions' => 'Preview granting permissions to a role (requires role + permissions params)',
          'revoke_permissions' => 'Preview revoking permissions from a role (requires role + permissions params)',
          'create_content_type' => 'Preview creating a content type (requires machine_name param)',
          'delete_content_type' => 'Preview deleting a content type (requires id param)',
          'add_field' => 'Preview adding a field (requires bundle, field_name params)',
          'delete_field' => 'Preview deleting a field instance (requires bundle, field_name params)',
          'create_vocabulary' => 'Preview creating a vocabulary (requires machine_name param)',
          'create_view' => 'Preview creating a view (requires id param)',
        ],
      ];
    }

    $params = $input['params'] ?? [];

    return $this->configManagement->previewOperation($operation, $params);
  }

}
