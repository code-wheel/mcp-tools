<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_config\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_config\Service\ConfigManagementService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing configuration changes between active and sync storage.
 *
 * @Tool(
 *   id = "mcp_config_changes",
 *   label = @Translation("Get Config Changes"),
 *   description = @Translation("List configuration that differs from sync directory (what would be exported)."),
 *   category = "config",
 * )
 */
class GetConfigChanges extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return $this->configManagement->getConfigChanges();
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
      'has_changes' => [
        'type' => 'boolean',
        'label' => 'Has Changes',
        'description' => 'Whether there are differences between active and sync storage.',
      ],
      'total_changes' => [
        'type' => 'integer',
        'label' => 'Total Changes',
        'description' => 'Total number of configuration differences.',
      ],
      'summary' => [
        'type' => 'map',
        'label' => 'Summary',
        'description' => 'Count of changes by type (new, modified, deleted).',
      ],
      'changes' => [
        'type' => 'map',
        'label' => 'Changes',
        'description' => 'Configuration changes grouped by operation type.',
      ],
    ];
  }

}
