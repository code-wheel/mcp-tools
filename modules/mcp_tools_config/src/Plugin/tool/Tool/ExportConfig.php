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
  id: 'mcp_config_export',
  label: new TranslatableMarkup('Export Config'),
  description: new TranslatableMarkup('Export configuration to sync directory. DANGEROUS: Requires admin scope.'),
  operation: ToolOperation::Trigger,
  input_definitions: [
    'confirm' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Confirm'),
      description: new TranslatableMarkup('Must be set to true to confirm the export operation.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'exported' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Exported Count'),
      description: new TranslatableMarkup('Number of configuration objects exported.'),
    ),
    'deleted_from_sync' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Deleted from Sync'),
      description: new TranslatableMarkup('Number of configuration objects deleted from sync.'),
    ),
    'changes_resolved' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Changes Resolved'),
      description: new TranslatableMarkup('Number of changes that were resolved by export.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('Result message.'),
    ),
  ],
)]
class ExportConfig extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'config';


  /**
   * The config management.
   *
   * @var \Drupal\mcp_tools_config\Service\ConfigManagementService
   */
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
    $confirm = $input['confirm'] ?? FALSE;

    if (!$confirm) {
      return [
        'success' => FALSE,
        'error' => 'Configuration export requires explicit confirmation. Set confirm=true to proceed.',
        'code' => 'CONFIRMATION_REQUIRED',
        'warning' => 'This will overwrite the configuration sync directory. Use mcp_config_changes or mcp_config_preview first to review changes.',
      ];
    }

    return $this->configManagement->exportConfig();
  }

}
