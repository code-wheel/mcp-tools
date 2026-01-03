<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_scheduler\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_scheduler\Service\SchedulerService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool plugin for scheduling content publication.
 *
 * @Tool(
 *   id = "mcp_scheduler_publish",
 *   label = @Translation("Schedule Publish"),
 *   description = @Translation("Schedule content for publication at a specific date/time."),
 *   category = "scheduler",
 * )
 */
class SchedulePublish extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $timestamp = $input['timestamp'] ?? 0;

    if (empty($entityId)) {
      return ['success' => FALSE, 'error' => 'Entity ID is required.'];
    }

    if (empty($timestamp)) {
      return ['success' => FALSE, 'error' => 'Timestamp is required.'];
    }

    // Support both integer timestamp and date string.
    if (!is_numeric($timestamp)) {
      $parsedTime = strtotime($timestamp);
      if ($parsedTime === FALSE) {
        return ['success' => FALSE, 'error' => "Invalid timestamp format. Provide a Unix timestamp or parseable date string."];
      }
      $timestamp = $parsedTime;
    }

    return $this->schedulerService->schedulePublish($entityType, (int) $entityId, (int) $timestamp);
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
        'description' => 'The node ID to schedule',
        'required' => TRUE,
      ],
      'timestamp' => [
        'type' => 'string',
        'label' => 'Timestamp',
        'description' => 'Unix timestamp or date string (e.g., "2024-12-25 09:00:00") for scheduled publication',
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
      'scheduled_publish' => ['type' => 'string', 'label' => 'Scheduled Date'],
      'timestamp' => ['type' => 'integer', 'label' => 'Unix Timestamp'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
