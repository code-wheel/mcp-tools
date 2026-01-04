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
  id: 'mcp_tools_get_config_status',
  label: new TranslatableMarkup('Get Config Status'),
  description: new TranslatableMarkup('Check configuration synchronization status between active and sync storage.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'has_changes' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Has Pending Changes'),
      description: new TranslatableMarkup('True if there are differences between active and sync config storage.'),
    ),
    'total_changes' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Changes'),
      description: new TranslatableMarkup('Number of config objects that differ.'),
    ),
    'changes' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Change Details'),
      description: new TranslatableMarkup('Array of changes with name and type (create, update, delete, rename).'),
    ),
  ],
)]
class GetConfigStatus extends McpToolsToolBase {

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
    return [
      'success' => TRUE,
      'data' => $this->configAnalysis->getConfigStatus(),
    ];
  }

  

  

}
