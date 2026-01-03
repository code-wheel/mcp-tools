<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\QueueService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting queue status.
 *
 * @Tool(
 *   id = "mcp_tools_get_queue_status",
 *   label = @Translation("Get Queue Status"),
 *   description = @Translation("Get status of all queues including item counts and worker definitions."),
 *   category = "site_health",
 * )
 */
class GetQueueStatus extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected QueueService $queueService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->queueService = $container->get('mcp_tools.queue');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    return [
      'success' => TRUE,
      'data' => $this->queueService->getQueueStatus(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total_queues' => [
        'type' => 'integer',
        'label' => 'Total Queues',
      ],
      'queues_with_items' => [
        'type' => 'integer',
        'label' => 'Queues With Pending Items',
      ],
      'total_items' => [
        'type' => 'integer',
        'label' => 'Total Pending Items',
      ],
      'queues' => [
        'type' => 'list',
        'label' => 'Queue Details',
      ],
    ];
  }

}
