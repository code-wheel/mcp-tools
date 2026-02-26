<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_ultimate_cron\Plugin\tool\Tool;

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
  id: 'mcp_ultimate_cron_get_job',
  label: new TranslatableMarkup('Get Ultimate Cron Job'),
  description: new TranslatableMarkup('Get detailed information about a specific Ultimate Cron job.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Job ID'),
      description: new TranslatableMarkup('The Ultimate Cron job ID (machine name)'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Job ID'),
      description: new TranslatableMarkup('Machine name of the cron job. Use with RunJob, EnableJob, or DisableJob.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Job Title'),
      description: new TranslatableMarkup('Human-readable title of the cron job.'),
    ),
    'module' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Module'),
      description: new TranslatableMarkup('Module that provides this cron job.'),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status (enabled/disabled)'),
      description: new TranslatableMarkup('Current status: "enabled" (will run on schedule) or "disabled". Use EnableJob/DisableJob to change.'),
    ),
    'is_locked' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Is Locked'),
      description: new TranslatableMarkup('TRUE if the job is currently running and locked. Cannot run concurrently.'),
    ),
    'scheduler' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Scheduler Configuration'),
      description: new TranslatableMarkup('Scheduler settings: cron expression or rules defining when the job runs.'),
    ),
    'last_run' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Last Run Information'),
      description: new TranslatableMarkup('Last execution info: timestamp, duration, success status. Use GetJobLogs for full history.'),
    ),
  ],
)]
class GetJob extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'ultimate_cron';


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
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->ultimateCronService = $container->get('mcp_tools_ultimate_cron.ultimate_cron_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
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

}
