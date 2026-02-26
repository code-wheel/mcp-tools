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
  id: 'mcp_moderation_get_state',
  label: new TranslatableMarkup('Get Moderation State'),
  description: new TranslatableMarkup('Get the current moderation state of an entity and available transitions.'),
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
      description: new TranslatableMarkup('ID of the entity to check.'),
      required: TRUE,
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
      description: new TranslatableMarkup('Title/label of the entity.'),
    ),
    'current_state' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Current State'),
      description: new TranslatableMarkup('Current moderation state with id, label, and published status.'),
    ),
    'available_transitions' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Available Transitions'),
      description: new TranslatableMarkup('States the entity can transition to. Use state id with SetModerationState.'),
    ),
  ],
)]
class GetModerationState extends McpToolsToolBase {

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
    $entityType = $input['entity_type'] ?? 'node';
    $entityId = $input['entity_id'] ?? 0;

    if (empty($entityId)) {
      return ['success' => FALSE, 'error' => 'Entity ID is required.'];
    }

    return $this->moderationService->getModerationState($entityType, (int) $entityId);
  }

}
