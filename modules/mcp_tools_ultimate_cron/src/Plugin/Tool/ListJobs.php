<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_ultimate_cron\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_ultimate_cron\Service\UltimateCronService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing all Ultimate Cron jobs.
 *
 * @Tool(
 *   id = "mcp_ultimate_cron_list_jobs",
 *   label = @Translation("List Ultimate Cron Jobs"),
 *   description = @Translation("List all Ultimate Cron jobs with their status."),
 *   category = "ultimate_cron",
 * )
 */
class ListJobs extends ToolPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The Ultimate Cron service.
   *
   * @var \Drupal\mcp_tools_ultimate_cron\Service\UltimateCronService
   */
  protected UltimateCronService $ultimateCronService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->ultimateCronService = $container->get('mcp_tools_ultimate_cron.ultimate_cron_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    return $this->ultimateCronService->listJobs();
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
      'jobs' => [
        'type' => 'array',
        'label' => 'List of Ultimate Cron jobs',
      ],
      'count' => [
        'type' => 'integer',
        'label' => 'Total number of jobs',
      ],
    ];
  }

}
