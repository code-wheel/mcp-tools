<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_cron\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_cron\Service\CronService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting cron status.
 *
 * @Tool(
 *   id = "mcp_cron_get_status",
 *   label = @Translation("Get Cron Status"),
 *   description = @Translation("Get cron status including last run time, schedule, and registered jobs."),
 *   category = "cron",
 * )
 */
class GetCronStatus extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected CronService $cronService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->cronService = $container->get('mcp_tools_cron.cron_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    return [
      'success' => TRUE,
      'data' => $this->cronService->getCronStatus(),
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
        'label' => 'Last cron run time',
      ],
      'is_overdue' => [
        'type' => 'boolean',
        'label' => 'Is cron overdue',
      ],
      'jobs' => [
        'type' => 'list',
        'label' => 'Registered cron jobs',
      ],
    ];
  }

}
