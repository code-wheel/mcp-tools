<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\SiteHealthService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for checking cron status.
 *
 * @Tool(
 *   id = "mcp_tools_check_cron_status",
 *   label = @Translation("Check Cron Status"),
 *   description = @Translation("Check the status of Drupal cron including last run time and health assessment."),
 *   category = "site_health",
 * )
 */
class CheckCronStatus extends ToolPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The site health service.
   */
  protected SiteHealthService $siteHealth;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->siteHealth = $container->get('mcp_tools.site_health');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    return [
      'success' => TRUE,
      'data' => $this->siteHealth->getCronStatus(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'last_run' => [
        'type' => 'string',
        'label' => 'Last Run Time',
      ],
      'last_run_timestamp' => [
        'type' => 'integer',
        'label' => 'Last Run Timestamp',
      ],
      'seconds_since_last_run' => [
        'type' => 'integer',
        'label' => 'Seconds Since Last Run',
      ],
      'status' => [
        'type' => 'string',
        'label' => 'Health Status',
        'description' => 'One of: healthy, warning, critical, unknown',
      ],
    ];
  }

}
