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
  id: 'mcp_cron_run_queue',
  label: new TranslatableMarkup('Run Queue'),
  description: new TranslatableMarkup('Process items from a specific queue.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'queue' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Queue Name'),
      description: new TranslatableMarkup('The queue to process.'),
      required: TRUE,
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum items to process (default: 100).'),
      required: FALSE,
      default_value: 100,
    ),
  ],
  output_definitions: [
    'success' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Success status'),
      description: new TranslatableMarkup(''),
    ),
    'processed' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Items processed'),
      description: new TranslatableMarkup(''),
    ),
    'remaining' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Items remaining'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class RunQueue extends McpToolsToolBase {

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

  

  

}
