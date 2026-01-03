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
use Drupal\tool\TypedData\InputDefinition;

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
      description: new TranslatableMarkup(''),
    ),
    'queues_with_items' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Queues With Pending Items'),
      description: new TranslatableMarkup(''),
    ),
    'total_items' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Pending Items'),
      description: new TranslatableMarkup(''),
    ),
    'queues' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Queue Details'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GetQueueStatus extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'site_health';


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
