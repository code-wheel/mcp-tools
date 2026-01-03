<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_scheduler\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_scheduler\Service\SchedulerService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool plugin for listing scheduled content.
 *
 * @Tool(
 *   id = "mcp_scheduler_get_scheduled",
 *   label = @Translation("Get Scheduled Content"),
 *   description = @Translation("List content scheduled for publishing or unpublishing."),
 *   category = "scheduler",
 * )
 */
class GetScheduledContent extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected SchedulerService $schedulerService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->schedulerService = $container->get('mcp_tools_scheduler.scheduler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $type = $input['type'] ?? 'all';
    $limit = isset($input['limit']) ? (int) $input['limit'] : 50;

    if (!in_array($type, ['publish', 'unpublish', 'all'])) {
      return ['success' => FALSE, 'error' => "Invalid type '$type'. Must be 'publish', 'unpublish', or 'all'."];
    }

    if ($limit < 1 || $limit > 500) {
      return ['success' => FALSE, 'error' => 'Limit must be between 1 and 500.'];
    }

    return $this->schedulerService->getScheduledContent($type, $limit);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'type' => [
        'type' => 'string',
        'label' => 'Schedule Type',
        'description' => "Filter by schedule type: 'publish', 'unpublish', or 'all'",
        'required' => FALSE,
        'default' => 'all',
      ],
      'limit' => [
        'type' => 'integer',
        'label' => 'Limit',
        'description' => 'Maximum number of items to return (1-500)',
        'required' => FALSE,
        'default' => 50,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'items' => ['type' => 'array', 'label' => 'Scheduled Items'],
      'count' => ['type' => 'integer', 'label' => 'Item Count'],
      'filter' => ['type' => 'string', 'label' => 'Applied Filter'],
    ];
  }

}
