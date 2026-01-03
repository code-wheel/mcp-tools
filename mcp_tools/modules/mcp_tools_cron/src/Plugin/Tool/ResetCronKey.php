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
 * Tool for resetting the cron key.
 *
 * @Tool(
 *   id = "mcp_cron_reset_key",
 *   label = @Translation("Reset Cron Key"),
 *   description = @Translation("Generate a new cron key (invalidates old cron URL)."),
 *   category = "cron",
 * )
 */
class ResetCronKey extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    // Check admin access (this is a sensitive operation).
    $accessCheck = $this->accessManager->checkWriteAccess('admin', 'cron');
    if (!$accessCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $accessCheck['reason'],
        'code' => $accessCheck['code'] ?? 'ACCESS_DENIED',
      ];
    }

    $result = $this->cronService->resetCronKey();

    if ($result['success']) {
      $this->auditLogger->log('reset', 'cron_key', 'system', []);
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
      'new_key' => [
        'type' => 'string',
        'label' => 'New cron key',
      ],
    ];
  }

}
