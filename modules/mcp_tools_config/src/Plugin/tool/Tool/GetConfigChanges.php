<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_config\Plugin\tool\Tool;

use Drupal\mcp_tools_config\Service\ConfigManagementService;
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
  id: 'mcp_config_changes',
  label: new TranslatableMarkup('Get Config Changes'),
  description: new TranslatableMarkup('List configuration that differs from sync directory (what would be exported).'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'has_changes' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Has Changes'),
      description: new TranslatableMarkup('Whether there are differences between active and sync storage.'),
    ),
    'total_changes' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Changes'),
      description: new TranslatableMarkup('Total number of configuration differences.'),
    ),
    'summary' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Summary'),
      description: new TranslatableMarkup('Count of changes by type (new, modified, deleted).'),
    ),
    'changes' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Changes'),
      description: new TranslatableMarkup('Configuration changes grouped by operation type.'),
    ),
  ],
)]
class GetConfigChanges extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'config';


  protected ConfigManagementService $configManagement;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configManagement = $container->get('mcp_tools_config.config_management');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return $this->configManagement->getConfigChanges();
  }

  

  

}
