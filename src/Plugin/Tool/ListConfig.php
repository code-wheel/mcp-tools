<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\ConfigAnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing configuration names.
 *
 * @Tool(
 *   id = "mcp_tools_list_config",
 *   label = @Translation("List Configuration"),
 *   description = @Translation("List all configuration object names, optionally filtered by prefix."),
 *   category = "config",
 * )
 */
class ListConfig extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $prefix = $input['prefix'] ?? NULL;

    return [
      'success' => TRUE,
      'data' => $this->configAnalysis->listConfig($prefix),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'prefix' => [
        'type' => 'string',
        'label' => 'Prefix Filter',
        'description' => 'Filter by prefix (e.g., "system.", "node.type.", "views.view.").',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'prefix' => [
        'type' => 'string',
        'label' => 'Prefix Used',
      ],
      'total' => [
        'type' => 'integer',
        'label' => 'Total Configuration Objects',
      ],
      'names' => [
        'type' => 'list',
        'label' => 'Configuration Names',
      ],
    ];
  }

}
