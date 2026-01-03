<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_ultimate_cron\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_ultimate_cron\Service\UltimateCronService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting details of a specific Ultimate Cron job.
 *
 * @Tool(
 *   id = "mcp_ultimate_cron_get_job",
 *   label = @Translation("Get Ultimate Cron Job"),
 *   description = @Translation("Get detailed information about a specific Ultimate Cron job."),
 *   category = "ultimate_cron",
 * )
 */
class GetJob extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    if (empty($id)) {
      return [
        'success' => FALSE,
        'error' => 'Job ID is required.',
        'code' => 'VALIDATION_ERROR',
      ];
    }

    return $this->ultimateCronService->getJob($id);
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
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Job ID'],
      'title' => ['type' => 'string', 'label' => 'Job Title'],
      'module' => ['type' => 'string', 'label' => 'Module'],
      'status' => ['type' => 'string', 'label' => 'Status (enabled/disabled)'],
      'is_locked' => ['type' => 'boolean', 'label' => 'Is Locked'],
      'scheduler' => ['type' => 'object', 'label' => 'Scheduler Configuration'],
      'last_run' => ['type' => 'object', 'label' => 'Last Run Information'],
    ];
  }

}
