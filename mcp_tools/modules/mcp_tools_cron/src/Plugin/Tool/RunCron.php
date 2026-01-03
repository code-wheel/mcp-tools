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
 * Tool for running cron.
 *
 * @Tool(
 *   id = "mcp_cron_run",
 *   label = @Translation("Run Cron"),
 *   description = @Translation("Execute all cron jobs immediately."),
 *   category = "cron",
 * )
 */
class RunCron extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    // Check write access (running cron is a write operation).
    $accessCheck = $this->accessManager->checkWriteAccess('run', 'cron');
    if (!$accessCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $accessCheck['reason'],
        'code' => $accessCheck['code'] ?? 'ACCESS_DENIED',
      ];
    }

    $result = $this->cronService->runCron();

    if ($result['success']) {
      $this->auditLogger->log('run', 'cron', 'all', [
        'duration' => $result['duration_seconds'] ?? 0,
      ]);
    }

    return $result;
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
      'success' => [
        'type' => 'boolean',
        'label' => 'Success status',
      ],
      'duration_seconds' => [
        'type' => 'number',
        'label' => 'Duration in seconds',
      ],
    ];
  }

}
