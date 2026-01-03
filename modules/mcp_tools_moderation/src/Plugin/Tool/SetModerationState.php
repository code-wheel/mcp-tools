<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_moderation\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_moderation\Service\ModerationService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for setting moderation state of an entity.
 *
 * @Tool(
 *   id = "mcp_moderation_set_state",
 *   label = @Translation("Set Moderation State"),
 *   description = @Translation("Change the moderation state of an entity (creates a new revision)."),
 *   category = "moderation",
 * )
 */
class SetModerationState extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ModerationService $moderationService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->moderationService = $container->get('mcp_tools_moderation.moderation');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
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

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'required' => FALSE, 'default' => 'node'],
      'entity_id' => ['type' => 'integer', 'label' => 'Entity ID', 'required' => TRUE],
      'state' => ['type' => 'string', 'label' => 'Moderation State', 'required' => TRUE],
      'revision_message' => ['type' => 'string', 'label' => 'Revision Message', 'required' => FALSE],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type'],
      'entity_id' => ['type' => 'integer', 'label' => 'Entity ID'],
      'label' => ['type' => 'string', 'label' => 'Entity Label'],
      'previous_state' => ['type' => 'string', 'label' => 'Previous State'],
      'new_state' => ['type' => 'string', 'label' => 'New State'],
      'changed' => ['type' => 'boolean', 'label' => 'State Changed'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
