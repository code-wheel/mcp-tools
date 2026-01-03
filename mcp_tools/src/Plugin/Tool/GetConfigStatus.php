<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\ConfigAnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting configuration sync status.
 *
 * @Tool(
 *   id = "mcp_tools_get_config_status",
 *   label = @Translation("Get Config Status"),
 *   description = @Translation("Check configuration synchronization status between active and sync storage."),
 *   category = "config",
 * )
 */
class GetConfigStatus extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ConfigAnalysisService $configAnalysis;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->configAnalysis = $container->get('mcp_tools.config_analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    return [
      'success' => TRUE,
      'data' => $this->configAnalysis->getConfigStatus(),
    ];
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
        'label' => 'Has Pending Changes',
      ],
      'total_changes' => [
        'type' => 'integer',
        'label' => 'Total Changes',
      ],
      'changes' => [
        'type' => 'list',
        'label' => 'Change Details',
      ],
    ];
  }

}
