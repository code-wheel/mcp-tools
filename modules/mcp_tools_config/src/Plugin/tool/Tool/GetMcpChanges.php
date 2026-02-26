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

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_config_mcp_changes',
  label: new TranslatableMarkup('Get MCP Config Changes'),
  description: new TranslatableMarkup('List configuration entities created or modified via MCP tools.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Changes'),
      description: new TranslatableMarkup('Total number of configuration changes tracked via MCP.'),
    ),
    'by_operation' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('By Operation'),
      description: new TranslatableMarkup('Changes grouped by operation type.'),
    ),
    'changes' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Changes'),
      description: new TranslatableMarkup('List of all tracked configuration changes.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('Summary message.'),
    ),
  ],
)]
class GetMcpChanges extends McpToolsToolBase {

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
    return $this->configManagement->getMcpChanges();
  }

}
