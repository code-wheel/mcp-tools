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
  id: 'mcp_tools_list_config',
  label: new TranslatableMarkup('List Configuration'),
  description: new TranslatableMarkup('List all configuration object names, optionally filtered by prefix.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'prefix' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Prefix Filter'),
      description: new TranslatableMarkup('Filter by prefix (e.g., "system.", "node.type.", "views.view.").'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'prefix' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Prefix Used'),
      description: new TranslatableMarkup(''),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Configuration Objects'),
      description: new TranslatableMarkup(''),
    ),
    'names' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Configuration Names'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class ListConfig extends McpToolsToolBase {

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
    $prefix = $input['prefix'] ?? NULL;

    return [
      'success' => TRUE,
      'data' => $this->configAnalysis->listConfig($prefix),
    ];
  }

  

  

}
