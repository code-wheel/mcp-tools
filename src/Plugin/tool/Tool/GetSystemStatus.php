<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\SystemStatusService;
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
  id: 'mcp_tools_get_system_status',
  label: new TranslatableMarkup('Get System Status'),
  description: new TranslatableMarkup('Get Drupal system requirements and status report, including PHP info, database status, and module requirements.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'errors_only' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Errors Only'),
      description: new TranslatableMarkup('If true, only return warnings and errors. Otherwise return all status items.'),
      required: FALSE,
      default_value: FALSE,
    ),
  ],
  output_definitions: [
    'requirements' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('System Requirements'),
      description: new TranslatableMarkup(''),
    ),
    'php' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('PHP Information'),
      description: new TranslatableMarkup(''),
    ),
    'database' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Database Status'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GetSystemStatus extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'site_health';


  protected SystemStatusService $systemStatus;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->systemStatus = $container->get('mcp_tools.system_status');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $errorsOnly = $input['errors_only'] ?? FALSE;

    return [
      'success' => TRUE,
      'data' => [
        'requirements' => $this->systemStatus->getRequirements($errorsOnly),
        'php' => $this->systemStatus->getPhpInfo(),
        'database' => $this->systemStatus->getDatabaseStatus(),
      ],
    ];
  }

  

  

}
