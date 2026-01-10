<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_ultimate_cron\Plugin\tool\Tool;

use CodeWheel\McpErrorCodes\ErrorCode;
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
  id: 'mcp_ultimate_cron_run',
  label: new TranslatableMarkup('Run Ultimate Cron Job'),
  description: new TranslatableMarkup('Execute a specific Ultimate Cron job immediately.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Job ID'),
      description: new TranslatableMarkup('The Ultimate Cron job ID (machine name) to run'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'success' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Success status'),
      description: new TranslatableMarkup('TRUE if the job completed successfully, FALSE if it failed or could not be run.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result message'),
      description: new TranslatableMarkup('Human-readable summary of the job execution result.'),
    ),
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Job ID'),
      description: new TranslatableMarkup('Machine name of the executed job.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Job Title'),
      description: new TranslatableMarkup('Human-readable title of the job.'),
    ),
    'duration_seconds' => new ContextDefinition(
      data_type: 'float',
      label: new TranslatableMarkup('Duration in seconds'),
      description: new TranslatableMarkup('How long the job took to execute. Useful for performance monitoring.'),
    ),
    'executed_at' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Execution timestamp'),
      description: new TranslatableMarkup('ISO 8601 timestamp when the job was executed.'),
    ),
  ],
)]
class RunJob extends McpToolsToolBase {

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
        'code' => ErrorCode::VALIDATION_ERROR,
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

  

  

}
