<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\SiteHealthService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_tools_check_cron_status',
  label: new TranslatableMarkup('Check Cron Status'),
  description: new TranslatableMarkup('Check the status of Drupal cron including last run time and health assessment.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'last_run' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Last Run Time'),
      description: new TranslatableMarkup('Human-readable last cron run time.'),
    ),
    'last_run_timestamp' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Last Run Timestamp'),
      description: new TranslatableMarkup('Unix timestamp of last cron run.'),
    ),
    'seconds_since_last_run' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Seconds Since Last Run'),
      description: new TranslatableMarkup('Seconds elapsed since last cron execution.'),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Health Status'),
      description: new TranslatableMarkup('Health assessment: healthy (<3h), warning (3-24h), critical (>24h), or unknown.'),
    ),
  ],
)]
class CheckCronStatus extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'site_health';


  /**
   * The site health service.
   */
  protected SiteHealthService $siteHealth;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->siteHealth = $container->get('mcp_tools.site_health');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return [
      'success' => TRUE,
      'data' => $this->siteHealth->getCronStatus(),
    ];
  }

}
