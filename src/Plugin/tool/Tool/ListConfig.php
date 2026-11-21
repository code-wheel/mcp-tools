<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\ConfigAnalysisService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
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
    'prefix' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Prefix Used'),
      description: new TranslatableMarkup('The prefix filter that was applied, or empty if none.'),
    ),
    'total' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Configuration Objects'),
      description: new TranslatableMarkup('Number of matching configuration objects.'),
    ),
    'names' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Configuration Names'),
      description: new TranslatableMarkup('Array of configuration object names. Use with GetConfig to view values.'),
    ),
  ],
)]
class ListConfig extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'config';


  /**
   * The config analysis.
   *
   * @var \Drupal\mcp_tools\Service\ConfigAnalysisService
   */
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
    $prefix = ($input['prefix'] ?? NULL) ?: NULL;

    return [
      'success' => TRUE,
      'data' => $this->configAnalysis->listConfig($prefix),
    ];
  }

}
