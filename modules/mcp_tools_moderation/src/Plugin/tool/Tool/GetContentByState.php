<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_moderation\Plugin\tool\Tool;

use Drupal\mcp_tools_moderation\Service\ModerationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_moderation_get_content_by_state',
  label: new TranslatableMarkup('Get Content by State'),
  description: new TranslatableMarkup('List all content in a specific moderation state within a workflow.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'workflow_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Workflow ID'),
      description: new TranslatableMarkup('Machine name of the workflow. Get from mcp_moderation_get_workflows.'),
      required: TRUE,
    ),
    'state' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Moderation State'),
      description: new TranslatableMarkup('State to filter by (e.g., "draft", "review", "published"). Get from mcp_moderation_get_workflow.'),
      required: TRUE,
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum results to return. Defaults to 50.'),
      required: FALSE,
      default_value: 50,
    ),
  ],
  output_definitions: [
    'workflow_id' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Workflow ID'),
      description: new TranslatableMarkup('The workflow queried.'),
    ),
    'workflow_label' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Workflow Label'),
      description: new TranslatableMarkup('Human-readable workflow name.'),
    ),
    'state' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('State'),
      description: new TranslatableMarkup('The state filter applied.'),
    ),
    'state_label' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('State Label'),
      description: new TranslatableMarkup('Human-readable state name.'),
    ),
    'total' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Content'),
      description: new TranslatableMarkup('Number of content items in this state.'),
    ),
    'content' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Content'),
      description: new TranslatableMarkup('Array of entities with id, type, bundle, label, and changed date.'),
    ),
  ],
)]
class GetContentByState extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'moderation';


  /**
   * The moderation service.
   *
   * @var \Drupal\mcp_tools_moderation\Service\ModerationService
   */
  protected ModerationService $moderationService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moderationService = $container->get('mcp_tools_moderation.moderation');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $workflowId = $input['workflow_id'] ?? '';
    $state = $input['state'] ?? '';
    $limit = $input['limit'] ?? 50;

    if (empty($workflowId)) {
      return ['success' => FALSE, 'error' => 'Workflow ID is required.'];
    }

    if (empty($state)) {
      return ['success' => FALSE, 'error' => 'State is required.'];
    }

    return $this->moderationService->getContentByState($workflowId, $state, (int) $limit);
  }

}
