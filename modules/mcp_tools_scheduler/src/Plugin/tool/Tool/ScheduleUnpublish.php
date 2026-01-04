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
  id: 'mcp_scheduler_unpublish',
  label: new TranslatableMarkup('Schedule Unpublish'),
  description: new TranslatableMarkup('Schedule content for unpublication at a specific date/time.'),
  operation: ToolOperation::Write,
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
      description: new TranslatableMarkup('The node ID to schedule'),
      required: TRUE,
    ),
    'timestamp' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Timestamp'),
      description: new TranslatableMarkup('Unix timestamp or date string (e.g., "2024-12-31 23:59:59") for scheduled unpublication'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'nid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The node that was scheduled.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('Title of the scheduled content.'),
    ),
    'scheduled_unpublish' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Scheduled Date'),
      description: new TranslatableMarkup('Human-readable scheduled unpublication date.'),
    ),
    'timestamp' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Unix Timestamp'),
      description: new TranslatableMarkup('Unix timestamp of scheduled unpublication.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success or error details.'),
    ),
  ],
)]
class ScheduleUnpublish extends McpToolsToolBase {

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

    return $this->schedulerService->scheduleUnpublish($entityType, (int) $entityId, (int) $timestamp);
  }

  

  

}
