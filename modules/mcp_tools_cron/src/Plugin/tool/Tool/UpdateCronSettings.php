<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_cron\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_cron\Service\CronService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_cron_update_settings',
  label: new TranslatableMarkup('Update Cron Settings'),
  description: new TranslatableMarkup('Update cron autorun threshold.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'threshold' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Threshold'),
      description: new TranslatableMarkup('Autorun threshold in seconds. Set to 0 to disable autorun.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'success' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Success status'),
      description: new TranslatableMarkup(''),
    ),
    'changes' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Applied changes'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class UpdateCronSettings extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'cron';


  protected CronService $cronService;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->cronService = $container->get('mcp_tools_cron.cron_service');
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    $instance->auditLogger = $container->get('mcp_tools.audit_logger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
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

  

  

}
