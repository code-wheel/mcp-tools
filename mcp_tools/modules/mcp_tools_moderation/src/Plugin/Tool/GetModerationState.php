<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_moderation\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_moderation\Service\ModerationService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting current moderation state of an entity.
 *
 * @Tool(
 *   id = "mcp_moderation_get_state",
 *   label = @Translation("Get Moderation State"),
 *   description = @Translation("Get the current moderation state of an entity and available transitions."),
 *   category = "moderation",
 * )
 */
class GetModerationState extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    if (empty($entityId)) {
      return ['success' => FALSE, 'error' => 'Entity ID is required.'];
    }

    return $this->moderationService->getModerationState($entityType, (int) $entityId);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'required' => FALSE, 'default' => 'node'],
      'entity_id' => ['type' => 'integer', 'label' => 'Entity ID', 'required' => TRUE],
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
      'current_state' => ['type' => 'object', 'label' => 'Current State'],
      'available_transitions' => ['type' => 'list', 'label' => 'Available Transitions'],
    ];
  }

}
