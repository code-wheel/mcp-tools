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
  id: 'mcp_ultimate_cron_list_jobs',
  label: new TranslatableMarkup('List Ultimate Cron Jobs'),
  description: new TranslatableMarkup('List all Ultimate Cron jobs with their status.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'jobs' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('List of Ultimate Cron jobs'),
      description: new TranslatableMarkup(''),
    ),
    'count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total number of jobs'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class ListJobs extends McpToolsToolBase {

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
    return $this->ultimateCronService->listJobs();
  }

  

  

}
