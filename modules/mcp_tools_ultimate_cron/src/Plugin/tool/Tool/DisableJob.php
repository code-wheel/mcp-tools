<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_ultimate_cron\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_ultimate_cron\Service\UltimateCronService;
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
  id: 'mcp_ultimate_cron_disable',
  label: new TranslatableMarkup('Disable Ultimate Cron Job'),
  description: new TranslatableMarkup('Disable an Ultimate Cron job to prevent it from running.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Job ID'),
      description: new TranslatableMarkup('The Ultimate Cron job ID (machine name) to disable'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'success' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Success status'),
      description: new TranslatableMarkup('TRUE if the job was disabled successfully, FALSE if an error occurred.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result message'),
      description: new TranslatableMarkup('Human-readable confirmation of the job status change.'),
    ),
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Job ID'),
      description: new TranslatableMarkup('Machine name of the disabled job. Use EnableJob to re-enable.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Job Title'),
      description: new TranslatableMarkup('Human-readable title of the job.'),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('New status'),
      description: new TranslatableMarkup('Current job status: "disabled". Job will not run until enabled.'),
    ),
    'changed' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Whether status was changed'),
      description: new TranslatableMarkup('TRUE if the job was actually disabled, FALSE if it was already disabled.'),
    ),
  ],
)]
class DisableJob extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'ultimate_cron';


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
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->ultimateCronService = $container->get('mcp_tools_ultimate_cron.ultimate_cron_service');
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    $instance->auditLogger = $container->get('mcp_tools.audit_logger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    // Check write access.
    $accessCheck = $this->accessManager->checkWriteAccess('disable', 'ultimate_cron_job');
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

    $result = $this->ultimateCronService->disableJob($id);

    // Log the operation if the status was actually changed.
    if ($result['success'] && ($result['data']['changed'] ?? FALSE)) {
      $this->auditLogger->log('disable', 'ultimate_cron_job', $id, [
        'title' => $result['data']['title'] ?? $id,
      ]);
    }

    return $result;
  }

  

  

}
