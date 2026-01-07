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
  id: 'mcp_ultimate_cron_logs',
  label: new TranslatableMarkup('Get Ultimate Cron Job Logs'),
  description: new TranslatableMarkup('Get recent execution logs for a specific Ultimate Cron job.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Job ID'),
      description: new TranslatableMarkup('The Ultimate Cron job ID (machine name)'),
      required: TRUE,
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum number of log entries to return (1-500)'),
      required: FALSE,
      default_value: 50,
    ),
  ],
  output_definitions: [
    'job_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Job ID'),
      description: new TranslatableMarkup('Machine name of the job whose logs were retrieved.'),
    ),
    'job_title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Job Title'),
      description: new TranslatableMarkup('Human-readable title of the job.'),
    ),
    'logs' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Log entries'),
      description: new TranslatableMarkup('Array of log entries with timestamp, duration, status, and message. Most recent first.'),
    ),
    'count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Number of entries returned'),
      description: new TranslatableMarkup('Actual number of log entries returned (may be less than limit).'),
    ),
    'limit' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit applied'),
      description: new TranslatableMarkup('Maximum entries that were requested. Increase to see more history.'),
    ),
  ],
)]
class GetJobLogs extends McpToolsToolBase {

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
    $limit = isset($input['limit']) ? (int) $input['limit'] : 50;

    if (empty($id)) {
      return [
        'success' => FALSE,
        'error' => 'Job ID is required.',
        'code' => 'VALIDATION_ERROR',
      ];
    }

    if ($limit < 1 || $limit > 500) {
      return [
        'success' => FALSE,
        'error' => 'Limit must be between 1 and 500.',
        'code' => 'VALIDATION_ERROR',
      ];
    }

    return $this->ultimateCronService->getJobLogs($id, $limit);
  }

  

  

}
