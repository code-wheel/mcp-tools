<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_moderation\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_moderation\Service\ModerationService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing content in a specific moderation state.
 *
 * @Tool(
 *   id = "mcp_moderation_get_content_by_state",
 *   label = @Translation("Get Content by State"),
 *   description = @Translation("List all content in a specific moderation state within a workflow."),
 *   category = "moderation",
 * )
 */
class GetContentByState extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'workflow_id' => ['type' => 'string', 'label' => 'Workflow ID', 'required' => TRUE],
      'state' => ['type' => 'string', 'label' => 'Moderation State', 'required' => TRUE],
      'limit' => ['type' => 'integer', 'label' => 'Limit', 'required' => FALSE, 'default' => 50],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'workflow_id' => ['type' => 'string', 'label' => 'Workflow ID'],
      'workflow_label' => ['type' => 'string', 'label' => 'Workflow Label'],
      'state' => ['type' => 'string', 'label' => 'State'],
      'state_label' => ['type' => 'string', 'label' => 'State Label'],
      'total' => ['type' => 'integer', 'label' => 'Total Content'],
      'content' => ['type' => 'list', 'label' => 'Content'],
    ];
  }

}
