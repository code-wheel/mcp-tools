<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\SystemStatusService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting system requirements and status.
 *
 * @Tool(
 *   id = "mcp_tools_get_system_status",
 *   label = @Translation("Get System Status"),
 *   description = @Translation("Get Drupal system requirements and status report, including PHP info, database status, and module requirements."),
 *   category = "site_health",
 * )
 */
class GetSystemStatus extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected SystemStatusService $systemStatus;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->systemStatus = $container->get('mcp_tools.system_status');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
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

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'errors_only' => [
        'type' => 'boolean',
        'label' => 'Errors Only',
        'description' => 'If true, only return warnings and errors. Otherwise return all status items.',
        'required' => FALSE,
        'default' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'requirements' => [
        'type' => 'map',
        'label' => 'System Requirements',
      ],
      'php' => [
        'type' => 'map',
        'label' => 'PHP Information',
      ],
      'database' => [
        'type' => 'map',
        'label' => 'Database Status',
      ],
    ];
  }

}
