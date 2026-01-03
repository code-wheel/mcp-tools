<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_ultimate_cron\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_ultimate_cron\Service\UltimateCronService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting logs of a specific Ultimate Cron job.
 *
 * @Tool(
 *   id = "mcp_ultimate_cron_logs",
 *   label = @Translation("Get Ultimate Cron Job Logs"),
 *   description = @Translation("Get recent execution logs for a specific Ultimate Cron job."),
 *   category = "ultimate_cron",
 * )
 */
class GetJobLogs extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $id = $input['id'] ?? '';
    $limit = isset($input['limit']) ? (int) $input['limit'] : 50;

    if (empty($id)) {
      return [
        'success' => FALSE,
        'error' => 'Job ID is required.',
        'code' => 'VALIDATION_ERROR',
      ];
    }

    if ($limit < 1 || $limit > 500) {
      return [
        'success' => FALSE,
        'error' => 'Limit must be between 1 and 500.',
        'code' => 'VALIDATION_ERROR',
      ];
    }

    return $this->ultimateCronService->getJobLogs($id, $limit);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => [
        'type' => 'string',
        'label' => 'Job ID',
        'description' => 'The Ultimate Cron job ID (machine name)',
        'required' => TRUE,
      ],
      'limit' => [
        'type' => 'integer',
        'label' => 'Limit',
        'description' => 'Maximum number of log entries to return (1-500)',
        'required' => FALSE,
        'default' => 50,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'job_id' => ['type' => 'string', 'label' => 'Job ID'],
      'job_title' => ['type' => 'string', 'label' => 'Job Title'],
      'logs' => ['type' => 'array', 'label' => 'Log entries'],
      'count' => ['type' => 'integer', 'label' => 'Number of entries returned'],
      'limit' => ['type' => 'integer', 'label' => 'Limit applied'],
    ];
  }

}
