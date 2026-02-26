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
  id: 'mcp_scheduler_get_scheduled',
  label: new TranslatableMarkup('Get Scheduled Content'),
  description: new TranslatableMarkup('List content scheduled for publishing or unpublishing.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Schedule Type'),
      description: new TranslatableMarkup("Filter by schedule type: 'publish', 'unpublish', or 'all'"),
      required: FALSE,
      default_value: 'all',
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum number of items to return (1-500)'),
      required: FALSE,
      default_value: 50,
    ),
  ],
  output_definitions: [
    'items' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Scheduled Items'),
      description: new TranslatableMarkup('Array of scheduled content with nid, title, schedule type, and timestamp.'),
    ),
    'count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Item Count'),
      description: new TranslatableMarkup('Number of scheduled items returned.'),
    ),
    'filter' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Applied Filter'),
      description: new TranslatableMarkup('Schedule type filter that was applied.'),
    ),
  ],
)]
class GetScheduledContent extends McpToolsToolBase {

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

}
