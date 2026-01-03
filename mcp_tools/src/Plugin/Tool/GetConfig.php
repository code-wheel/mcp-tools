<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\ConfigAnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for viewing configuration objects.
 *
 * @Tool(
 *   id = "mcp_tools_get_config",
 *   label = @Translation("Get Configuration"),
 *   description = @Translation("View a specific configuration object by name (e.g., 'system.site', 'node.type.article')."),
 *   category = "config",
 * )
 */
class GetConfig extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $name = $input['name'] ?? '';

    if (empty($name)) {
      return [
        'success' => FALSE,
        'error' => 'Configuration name is required.',
      ];
    }

    $data = $this->configAnalysis->getConfig($name);

    if (isset($data['error'])) {
      return [
        'success' => FALSE,
        'error' => $data['error'],
      ];
    }

    return [
      'success' => TRUE,
      'data' => $data,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'name' => [
        'type' => 'string',
        'label' => 'Configuration Name',
        'description' => 'The configuration object name (e.g., "system.site", "node.type.article").',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'name' => [
        'type' => 'string',
        'label' => 'Configuration Name',
      ],
      'data' => [
        'type' => 'map',
        'label' => 'Configuration Data',
      ],
    ];
  }

}
