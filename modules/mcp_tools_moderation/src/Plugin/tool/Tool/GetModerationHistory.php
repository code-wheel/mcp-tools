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
  id: 'mcp_moderation_get_history',
  label: new TranslatableMarkup('Get Moderation History'),
  description: new TranslatableMarkup('Get revision history of an entity with moderation state changes.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('Entity type (e.g., "node", "media"). Defaults to "node".'),
      required: FALSE,
      default_value: 'node',
    ),
    'entity_id' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('ID of the entity to get history for.'),
      required: TRUE,
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum revisions to return. Defaults to 50.'),
      required: FALSE,
      default_value: 50,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type queried.'),
    ),
    'entity_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The entity ID queried.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Label'),
      description: new TranslatableMarkup('Current title/label of the entity.'),
    ),
    'workflow_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Workflow ID'),
      description: new TranslatableMarkup('Workflow governing this entity.'),
    ),
    'total_revisions' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Revisions'),
      description: new TranslatableMarkup('Number of revisions returned.'),
    ),
    'revisions' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Revisions'),
      description: new TranslatableMarkup('Array of revisions with id, state, author, message, and timestamp.'),
    ),
  ],
)]
class GetModerationHistory extends McpToolsToolBase {

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
    $entityType = $input['entity_type'] ?? 'node';
    $entityId = $input['entity_id'] ?? 0;
    $limit = $input['limit'] ?? 50;

    if (empty($entityId)) {
      return ['success' => FALSE, 'error' => 'Entity ID is required.'];
    }

    return $this->moderationService->getModerationHistory($entityType, (int) $entityId, (int) $limit);
  }

  

  

}
