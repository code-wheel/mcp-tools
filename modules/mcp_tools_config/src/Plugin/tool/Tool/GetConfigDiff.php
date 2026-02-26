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
  id: 'mcp_config_diff',
  label: new TranslatableMarkup('Get Config Diff'),
  description: new TranslatableMarkup('Show diff between active and sync storage for a specific configuration.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'config_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Configuration Name'),
      description: new TranslatableMarkup('The configuration name to compare (e.g., "system.site", "node.type.article").'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'config_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Configuration Name'),
      description: new TranslatableMarkup('The configuration name that was compared.'),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup('Comparison status: unchanged, modified, new_in_active, deleted_from_active.'),
    ),
    'diff' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Diff'),
      description: new TranslatableMarkup('List of differences between active and sync.'),
    ),
    'sync_data' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Sync Data'),
      description: new TranslatableMarkup('Configuration data from sync storage.'),
    ),
    'active_data' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Active Data'),
      description: new TranslatableMarkup('Configuration data from active storage.'),
    ),
  ],
)]
class GetConfigDiff extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'config';


  /**
   * The config management.
   *
   * @var \Drupal\mcp_tools_config\Service\ConfigManagementService
   */
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
    $configName = $input['config_name'] ?? '';

    if (empty($configName)) {
      return [
        'success' => FALSE,
        'error' => 'config_name is required.',
      ];
    }

    return $this->configManagement->getConfigDiff($configName);
  }

}
