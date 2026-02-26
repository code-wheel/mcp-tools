<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_scheduler\Plugin\tool\Tool;

use Drupal\mcp_tools_scheduler\Service\SchedulerService;
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
  id: 'mcp_scheduler_cancel',
  label: new TranslatableMarkup('Cancel Schedule'),
  description: new TranslatableMarkup('Cancel scheduled publishing or unpublishing for content.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup("The entity type (currently only 'node' is supported)"),
      required: FALSE,
      default_value: 'node',
    ),
    'entity_id' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The node ID to cancel schedule for'),
      required: TRUE,
    ),
    'type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Schedule Type'),
      description: new TranslatableMarkup("Which schedule to cancel: 'publish', 'unpublish', or 'all'"),
      required: FALSE,
      default_value: 'all',
    ),
  ],
  output_definitions: [
    'nid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The node ID whose schedule was cancelled.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('Title of the content item.'),
    ),
    'cancelled' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Cancelled Schedules'),
      description: new TranslatableMarkup('List of cancelled schedule types: "publish", "unpublish", or both. Empty if nothing was scheduled.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation of what schedules were cancelled.'),
    ),
  ],
)]
class CancelSchedule extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'scheduler';


  /**
   * The scheduler service.
   *
   * @var \Drupal\mcp_tools_scheduler\Service\SchedulerService
   */
  protected SchedulerService $schedulerService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->schedulerService = $container->get('mcp_tools_scheduler.scheduler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
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

}
