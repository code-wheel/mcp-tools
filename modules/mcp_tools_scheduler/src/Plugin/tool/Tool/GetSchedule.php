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
  id: 'mcp_scheduler_get_schedule',
  label: new TranslatableMarkup('Get Schedule'),
  description: new TranslatableMarkup('Get scheduling information for a specific piece of content.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type (currently only \'node\' is supported)'),
      required: FALSE,
      default_value: 'node',
    ),
    'entity_id' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The node ID to get schedule info for'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'nid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The node ID queried.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('Title of the content item.'),
    ),
    'type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('Content type machine name (e.g., "article", "page").'),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Current Status'),
      description: new TranslatableMarkup('Current publication status: "published" or "unpublished".'),
    ),
    'scheduling_enabled' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Scheduling Enabled'),
      description: new TranslatableMarkup('TRUE if the content type supports scheduling, FALSE otherwise.'),
    ),
    'publish_on' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Scheduled Publish'),
      description: new TranslatableMarkup('Scheduled publish date info: timestamp, formatted. NULL if not scheduled. Use SetSchedule to modify.'),
    ),
    'unpublish_on' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Scheduled Unpublish'),
      description: new TranslatableMarkup('Scheduled unpublish date info: timestamp, formatted. NULL if not scheduled. Use SetSchedule to modify.'),
    ),
    'has_schedule' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Has Active Schedule'),
      description: new TranslatableMarkup('TRUE if any schedule (publish or unpublish) is set. Use CancelSchedule to remove.'),
    ),
  ],
)]
class GetSchedule extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'scheduler';


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

    if (empty($entityId)) {
      return ['success' => FALSE, 'error' => 'Entity ID is required.'];
    }

    return $this->schedulerService->getSchedule($entityType, (int) $entityId);
  }

  

  

}
