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
  id: 'mcp_cron_run',
  label: new TranslatableMarkup('Run Cron'),
  description: new TranslatableMarkup('Execute all cron jobs immediately.'),
  operation: ToolOperation::Write,
  input_definitions: [],
  output_definitions: [
    'success' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Success status'),
      description: new TranslatableMarkup('True if all cron jobs completed successfully.'),
    ),
    'duration_seconds' => new ContextDefinition(
      data_type: 'float',
      label: new TranslatableMarkup('Duration in seconds'),
      description: new TranslatableMarkup('Total execution time. High values may indicate performance issues.'),
    ),
  ],
)]
class RunCron extends McpToolsToolBase {

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

  

  

}
