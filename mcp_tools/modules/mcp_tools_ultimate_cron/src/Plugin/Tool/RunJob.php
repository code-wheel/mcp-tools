<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_ultimate_cron\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_ultimate_cron\Service\UltimateCronService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for running a specific Ultimate Cron job immediately.
 *
 * @Tool(
 *   id = "mcp_ultimate_cron_run",
 *   label = @Translation("Run Ultimate Cron Job"),
 *   description = @Translation("Execute a specific Ultimate Cron job immediately."),
 *   category = "ultimate_cron",
 * )
 */
class RunJob extends ToolPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The Ultimate Cron service.
   *
   * @var \Drupal\mcp_tools_ultimate_cron\Service\UltimateCronService
   */
  protected UltimateCronService $ultimateCronService;

  /**
   * The access manager.
   *
   * @var \Drupal\mcp_tools\Service\AccessManager
   */
  protected AccessManager $accessManager;

  /**
   * The audit logger.
   *
   * @var \Drupal\mcp_tools\Service\AuditLogger
   */
  protected AuditLogger $auditLogger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->ultimateCronService = $container->get('mcp_tools_ultimate_cron.ultimate_cron_service');
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    $instance->auditLogger = $container->get('mcp_tools.audit_logger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    // Check write access (running a cron job is a write operation).
    $accessCheck = $this->accessManager->checkWriteAccess('run', 'ultimate_cron_job');
    if (!$accessCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $accessCheck['reason'],
        'code' => $accessCheck['code'] ?? 'ACCESS_DENIED',
      ];
    }

    $id = $input['id'] ?? '';

    if (empty($id)) {
      return [
        'success' => FALSE,
        'error' => 'Job ID is required.',
        'code' => 'VALIDATION_ERROR',
      ];
    }

    $result = $this->ultimateCronService->runJob($id);

    // Log the operation.
    if ($result['success']) {
      $this->auditLogger->log('run', 'ultimate_cron_job', $id, [
        'duration' => $result['data']['duration_seconds'] ?? 0,
      ]);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => [
        'type' => 'string',
        'label' => 'Job ID',
        'description' => 'The Ultimate Cron job ID (machine name) to run',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'success' => ['type' => 'boolean', 'label' => 'Success status'],
      'message' => ['type' => 'string', 'label' => 'Result message'],
      'id' => ['type' => 'string', 'label' => 'Job ID'],
      'title' => ['type' => 'string', 'label' => 'Job Title'],
      'duration_seconds' => ['type' => 'number', 'label' => 'Duration in seconds'],
      'executed_at' => ['type' => 'string', 'label' => 'Execution timestamp'],
    ];
  }

}
