<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_config\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_config\Service\ConfigManagementService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for exporting configuration to sync directory.
 *
 * DANGEROUS: This tool requires admin scope and exports all active
 * configuration to the sync directory.
 *
 * @Tool(
 *   id = "mcp_config_export",
 *   label = @Translation("Export Config"),
 *   description = @Translation("Export configuration to sync directory. DANGEROUS: Requires admin scope."),
 *   category = "config",
 * )
 */
class ExportConfig extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'confirm' => [
        'type' => 'boolean',
        'label' => 'Confirm',
        'description' => 'Must be set to true to confirm the export operation.',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'exported' => [
        'type' => 'integer',
        'label' => 'Exported Count',
        'description' => 'Number of configuration objects exported.',
      ],
      'deleted_from_sync' => [
        'type' => 'integer',
        'label' => 'Deleted from Sync',
        'description' => 'Number of configuration objects deleted from sync.',
      ],
      'changes_resolved' => [
        'type' => 'integer',
        'label' => 'Changes Resolved',
        'description' => 'Number of changes that were resolved by export.',
      ],
      'message' => [
        'type' => 'string',
        'label' => 'Message',
        'description' => 'Result message.',
      ],
    ];
  }

}
