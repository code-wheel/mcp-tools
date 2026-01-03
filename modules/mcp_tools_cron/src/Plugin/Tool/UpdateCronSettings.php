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
 * Tool for updating cron settings.
 *
 * @Tool(
 *   id = "mcp_cron_update_settings",
 *   label = @Translation("Update Cron Settings"),
 *   description = @Translation("Update cron autorun threshold."),
 *   category = "cron",
 * )
 */
class UpdateCronSettings extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $accessCheck = $this->accessManager->checkWriteAccess('update', 'cron_settings');
    if (!$accessCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $accessCheck['reason'],
        'code' => $accessCheck['code'] ?? 'ACCESS_DENIED',
      ];
    }

    $threshold = isset($input['threshold']) ? (int) $input['threshold'] : NULL;

    $result = $this->cronService->updateSettings($threshold);

    if ($result['success']) {
      $this->auditLogger->log('update', 'cron_settings', 'threshold', [
        'changes' => $result['changes'] ?? [],
      ]);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'threshold' => [
        'type' => 'integer',
        'label' => 'Threshold',
        'description' => 'Autorun threshold in seconds. Set to 0 to disable autorun.',
        'required' => TRUE,
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
      'changes' => [
        'type' => 'object',
        'label' => 'Applied changes',
      ],
    ];
  }

}
