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
use Drupal\tool\TypedData\InputDefinition;

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
      description: new TranslatableMarkup(''),
    ),
    'last_run_timestamp' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Last Run Timestamp'),
      description: new TranslatableMarkup(''),
    ),
    'seconds_since_last_run' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Seconds Since Last Run'),
      description: new TranslatableMarkup(''),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Health Status'),
      description: new TranslatableMarkup('One of: healthy, warning, critical, unknown'),
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
