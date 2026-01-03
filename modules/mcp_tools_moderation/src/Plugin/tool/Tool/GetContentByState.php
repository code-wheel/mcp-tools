<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_moderation\Plugin\tool\Tool;

use Drupal\mcp_tools_moderation\Service\ModerationService;
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
  id: 'mcp_moderation_get_content_by_state',
  label: new TranslatableMarkup('Get Content by State'),
  description: new TranslatableMarkup('List all content in a specific moderation state within a workflow.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'workflow_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Workflow ID'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'state' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Moderation State'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup(''),
      required: FALSE,
      default_value: 50,
    ),
  ],
  output_definitions: [
    'workflow_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Workflow ID'),
      description: new TranslatableMarkup(''),
    ),
    'workflow_label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Workflow Label'),
      description: new TranslatableMarkup(''),
    ),
    'state' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('State'),
      description: new TranslatableMarkup(''),
    ),
    'state_label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('State Label'),
      description: new TranslatableMarkup(''),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Content'),
      description: new TranslatableMarkup(''),
    ),
    'content' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Content'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GetContentByState extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'moderation';


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
