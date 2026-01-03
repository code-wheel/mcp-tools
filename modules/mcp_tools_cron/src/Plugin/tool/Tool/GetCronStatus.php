<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_cron\Plugin\tool\Tool;

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
  id: 'mcp_cron_get_status',
  label: new TranslatableMarkup('Get Cron Status'),
  description: new TranslatableMarkup('Get cron status including last run time, schedule, and registered jobs.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'last_run' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Last cron run time'),
      description: new TranslatableMarkup(''),
    ),
    'is_overdue' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Is cron overdue'),
      description: new TranslatableMarkup(''),
    ),
    'jobs' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Registered cron jobs'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GetCronStatus extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'cron';


  protected CronService $cronService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->cronService = $container->get('mcp_tools_cron.cron_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return [
      'success' => TRUE,
      'data' => $this->cronService->getCronStatus(),
    ];
  }

  

  

}
