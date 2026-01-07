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
  id: 'mcp_moderation_set_state',
  label: new TranslatableMarkup('Set Moderation State'),
  description: new TranslatableMarkup('Change the moderation state of an entity (creates a new revision).'),
  operation: ToolOperation::Write,
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
      description: new TranslatableMarkup('ID of the entity to update.'),
      required: TRUE,
    ),
    'state' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Moderation State'),
      description: new TranslatableMarkup('Target state (e.g., "draft", "published"). Get from GetModerationState.'),
      required: TRUE,
    ),
    'revision_message' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Revision Message'),
      description: new TranslatableMarkup('Optional log message for the revision.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type updated.'),
    ),
    'entity_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The entity ID updated.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Label'),
      description: new TranslatableMarkup('Title/label of the entity.'),
    ),
    'previous_state' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Previous State'),
      description: new TranslatableMarkup('State before the transition.'),
    ),
    'new_state' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('New State'),
      description: new TranslatableMarkup('Current state after the transition.'),
    ),
    'changed' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('State Changed'),
      description: new TranslatableMarkup('True if state was changed, false if already in target state.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success or error details.'),
    ),
  ],
)]
class SetModerationState extends McpToolsToolBase {

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
    $state = $input['state'] ?? '';

    if (empty($entityId)) {
      return ['success' => FALSE, 'error' => 'Entity ID is required.'];
    }

    if (empty($state)) {
      return ['success' => FALSE, 'error' => 'State is required.'];
    }

    $revisionMessage = $input['revision_message'] ?? '';

    return $this->moderationService->setModerationState($entityType, (int) $entityId, $state, $revisionMessage);
  }

  

  

}
