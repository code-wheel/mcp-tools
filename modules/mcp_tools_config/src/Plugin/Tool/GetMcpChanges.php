<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_config\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_config\Service\ConfigManagementService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing configuration changes made via MCP.
 *
 * @Tool(
 *   id = "mcp_config_mcp_changes",
 *   label = @Translation("Get MCP Config Changes"),
 *   description = @Translation("List configuration entities created or modified via MCP tools."),
 *   category = "config",
 * )
 */
class GetMcpChanges extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return $this->configManagement->getMcpChanges();
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
      'total' => [
        'type' => 'integer',
        'label' => 'Total Changes',
        'description' => 'Total number of configuration changes tracked via MCP.',
      ],
      'by_operation' => [
        'type' => 'map',
        'label' => 'By Operation',
        'description' => 'Changes grouped by operation type.',
      ],
      'changes' => [
        'type' => 'list',
        'label' => 'Changes',
        'description' => 'List of all tracked configuration changes.',
      ],
      'message' => [
        'type' => 'string',
        'label' => 'Message',
        'description' => 'Summary message.',
      ],
    ];
  }

}
