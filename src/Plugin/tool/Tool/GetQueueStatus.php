<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\QueueService;
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
  id: 'mcp_tools_get_queue_status',
  label: new TranslatableMarkup('Get Queue Status'),
  description: new TranslatableMarkup('Get status of all queues including item counts and worker definitions.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total_queues' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Queues'),
      description: new TranslatableMarkup('Number of queue workers defined on the site.'),
    ),
    'queues_with_items' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Queues With Pending Items'),
      description: new TranslatableMarkup('Number of queues that have items waiting to be processed.'),
    ),
    'total_items' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Pending Items'),
      description: new TranslatableMarkup('Total items across all queues waiting to be processed.'),
    ),
    'queues' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Queue Details'),
      description: new TranslatableMarkup('Array of queues with name, title, item_count, and cron settings. Use name with RunQueue.'),
    ),
  ],
)]
class GetQueueStatus extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'site_health';


  /**
   * The queue service.
   *
   * @var \Drupal\mcp_tools\Service\QueueService
   */
  protected QueueService $queueService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->queueService = $container->get('mcp_tools.queue');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return [
      'success' => TRUE,
      'data' => $this->queueService->getQueueStatus(),
    ];
  }

}
