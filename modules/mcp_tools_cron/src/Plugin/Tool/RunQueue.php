<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_cron\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_cron\Service\CronService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for running a specific queue.
 *
 * @Tool(
 *   id = "mcp_cron_run_queue",
 *   label = @Translation("Run Queue"),
 *   description = @Translation("Process items from a specific queue."),
 *   category = "cron",
 * )
 */
class RunQueue extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected CronService $cronService;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->cronService = $container->get('mcp_tools_cron.cron_service');
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    $instance->auditLogger = $container->get('mcp_tools.audit_logger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    // Check write access.
    $accessCheck = $this->accessManager->checkWriteAccess('run', 'queue');
    if (!$accessCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $accessCheck['reason'],
        'code' => $accessCheck['code'] ?? 'ACCESS_DENIED',
      ];
    }

    $queueName = $input['queue'] ?? '';
    $limit = $input['limit'] ?? 100;

    if (empty($queueName)) {
      return [
        'success' => FALSE,
        'error' => 'queue is required.',
      ];
    }

    $result = $this->cronService->runQueue($queueName, (int) $limit);

    if ($result['success']) {
      $this->auditLogger->log('run', 'queue', $queueName, [
        'processed' => $result['processed'] ?? 0,
      ]);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'queue' => [
        'type' => 'string',
        'label' => 'Queue Name',
        'description' => 'The queue to process.',
        'required' => TRUE,
      ],
      'limit' => [
        'type' => 'integer',
        'label' => 'Limit',
        'description' => 'Maximum items to process (default: 100).',
        'required' => FALSE,
        'default' => 100,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'success' => [
        'type' => 'boolean',
        'label' => 'Success status',
      ],
      'processed' => [
        'type' => 'integer',
        'label' => 'Items processed',
      ],
      'remaining' => [
        'type' => 'integer',
        'label' => 'Items remaining',
      ],
    ];
  }

}
