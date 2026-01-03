<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_scheduler\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_scheduler\Service\SchedulerService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool plugin for canceling scheduled publish/unpublish.
 *
 * @Tool(
 *   id = "mcp_scheduler_cancel",
 *   label = @Translation("Cancel Schedule"),
 *   description = @Translation("Cancel scheduled publishing or unpublishing for content."),
 *   category = "scheduler",
 * )
 */
class CancelSchedule extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $entityType = $input['entity_type'] ?? 'node';
    $entityId = $input['entity_id'] ?? 0;
    $type = $input['type'] ?? 'all';

    if (empty($entityId)) {
      return ['success' => FALSE, 'error' => 'Entity ID is required.'];
    }

    if (!in_array($type, ['publish', 'unpublish', 'all'])) {
      return ['success' => FALSE, 'error' => "Invalid type '$type'. Must be 'publish', 'unpublish', or 'all'."];
    }

    return $this->schedulerService->cancelSchedule($entityType, (int) $entityId, $type);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'entity_type' => [
        'type' => 'string',
        'label' => 'Entity Type',
        'description' => "The entity type (currently only 'node' is supported)",
        'required' => FALSE,
        'default' => 'node',
      ],
      'entity_id' => [
        'type' => 'integer',
        'label' => 'Entity ID',
        'description' => 'The node ID to cancel schedule for',
        'required' => TRUE,
      ],
      'type' => [
        'type' => 'string',
        'label' => 'Schedule Type',
        'description' => "Which schedule to cancel: 'publish', 'unpublish', or 'all'",
        'required' => FALSE,
        'default' => 'all',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'nid' => ['type' => 'integer', 'label' => 'Node ID'],
      'title' => ['type' => 'string', 'label' => 'Title'],
      'cancelled' => ['type' => 'array', 'label' => 'Cancelled Schedules'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
