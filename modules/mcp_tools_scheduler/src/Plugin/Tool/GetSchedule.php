<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_scheduler\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_scheduler\Service\SchedulerService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool plugin for getting schedule info for specific content.
 *
 * @Tool(
 *   id = "mcp_scheduler_get_schedule",
 *   label = @Translation("Get Schedule"),
 *   description = @Translation("Get scheduling information for a specific piece of content."),
 *   category = "scheduler",
 * )
 */
class GetSchedule extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    if (empty($entityId)) {
      return ['success' => FALSE, 'error' => 'Entity ID is required.'];
    }

    return $this->schedulerService->getSchedule($entityType, (int) $entityId);
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
        'description' => 'The node ID to get schedule info for',
        'required' => TRUE,
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
      'type' => ['type' => 'string', 'label' => 'Content Type'],
      'status' => ['type' => 'string', 'label' => 'Current Status'],
      'scheduling_enabled' => ['type' => 'boolean', 'label' => 'Scheduling Enabled'],
      'publish_on' => ['type' => 'object', 'label' => 'Scheduled Publish'],
      'unpublish_on' => ['type' => 'object', 'label' => 'Scheduled Unpublish'],
      'has_schedule' => ['type' => 'boolean', 'label' => 'Has Active Schedule'],
    ];
  }

}
