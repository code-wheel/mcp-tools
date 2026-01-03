<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_config\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_config\Service\ConfigManagementService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for showing diff between active and sync configuration.
 *
 * @Tool(
 *   id = "mcp_config_diff",
 *   label = @Translation("Get Config Diff"),
 *   description = @Translation("Show diff between active and sync storage for a specific configuration."),
 *   category = "config",
 * )
 */
class GetConfigDiff extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $configName = $input['config_name'] ?? '';

    if (empty($configName)) {
      return [
        'success' => FALSE,
        'error' => 'config_name is required.',
      ];
    }

    return $this->configManagement->getConfigDiff($configName);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'config_name' => [
        'type' => 'string',
        'label' => 'Configuration Name',
        'description' => 'The configuration name to compare (e.g., "system.site", "node.type.article").',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'config_name' => [
        'type' => 'string',
        'label' => 'Configuration Name',
        'description' => 'The configuration name that was compared.',
      ],
      'status' => [
        'type' => 'string',
        'label' => 'Status',
        'description' => 'Comparison status: unchanged, modified, new_in_active, deleted_from_active.',
      ],
      'diff' => [
        'type' => 'list',
        'label' => 'Diff',
        'description' => 'List of differences between active and sync.',
      ],
      'sync_data' => [
        'type' => 'map',
        'label' => 'Sync Data',
        'description' => 'Configuration data from sync storage.',
      ],
      'active_data' => [
        'type' => 'map',
        'label' => 'Active Data',
        'description' => 'Configuration data from active storage.',
      ],
    ];
  }

}
