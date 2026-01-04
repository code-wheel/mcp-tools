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
  id: 'mcp_cron_reset_key',
  label: new TranslatableMarkup('Reset Cron Key'),
  description: new TranslatableMarkup('Generate a new cron key (invalidates old cron URL).'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [],
  output_definitions: [
    'success' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Success status'),
      description: new TranslatableMarkup('True if new key was generated successfully.'),
    ),
    'new_key' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('New cron key'),
      description: new TranslatableMarkup('New cron key. Update any external cron triggers with this value.'),
    ),
  ],
)]
class ResetCronKey extends McpToolsToolBase {

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

  

  

}
