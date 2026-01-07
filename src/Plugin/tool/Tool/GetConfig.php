<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\ConfigAnalysisService;
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
  id: 'mcp_tools_get_config',
  label: new TranslatableMarkup('Get Configuration'),
  description: new TranslatableMarkup('View a specific configuration object by name (e.g., \'system.site\', \'node.type.article\').'),
  operation: ToolOperation::Read,
  input_definitions: [
    'name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Configuration Name'),
      description: new TranslatableMarkup('The configuration object name (e.g., "system.site", "node.type.article").'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Configuration Name'),
      description: new TranslatableMarkup('The configuration object name that was retrieved.'),
    ),
    'data' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Configuration Data'),
      description: new TranslatableMarkup('The configuration values as a nested object. Structure varies by config type.'),
    ),
  ],
)]
class GetConfig extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'config';


  protected ConfigAnalysisService $configAnalysis;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configAnalysis = $container->get('mcp_tools.config_analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
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

  

  

}
