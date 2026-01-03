<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_moderation\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_moderation\Service\ModerationService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting revision history with moderation states.
 *
 * @Tool(
 *   id = "mcp_moderation_get_history",
 *   label = @Translation("Get Moderation History"),
 *   description = @Translation("Get revision history of an entity with moderation state changes."),
 *   category = "moderation",
 * )
 */
class GetModerationHistory extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $limit = $input['limit'] ?? 50;

    if (empty($entityId)) {
      return ['success' => FALSE, 'error' => 'Entity ID is required.'];
    }

    return $this->moderationService->getModerationHistory($entityType, (int) $entityId, (int) $limit);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'required' => FALSE, 'default' => 'node'],
      'entity_id' => ['type' => 'integer', 'label' => 'Entity ID', 'required' => TRUE],
      'limit' => ['type' => 'integer', 'label' => 'Limit', 'required' => FALSE, 'default' => 50],
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
      'workflow_id' => ['type' => 'string', 'label' => 'Workflow ID'],
      'total_revisions' => ['type' => 'integer', 'label' => 'Total Revisions'],
      'revisions' => ['type' => 'list', 'label' => 'Revisions'],
    ];
  }

}
