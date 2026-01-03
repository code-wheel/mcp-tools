<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_moderation\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_moderation\Service\ModerationService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting details of a specific workflow.
 *
 * @Tool(
 *   id = "mcp_moderation_get_workflow",
 *   label = @Translation("Get Workflow"),
 *   description = @Translation("Get details of a specific content moderation workflow including states and transitions."),
 *   category = "moderation",
 * )
 */
class GetWorkflow extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $id = $input['id'] ?? '';

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Workflow ID is required.'];
    }

    return $this->moderationService->getWorkflow($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Workflow ID', 'required' => TRUE],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Workflow ID'],
      'label' => ['type' => 'string', 'label' => 'Workflow Label'],
      'states' => ['type' => 'object', 'label' => 'States'],
      'transitions' => ['type' => 'object', 'label' => 'Transitions'],
      'entity_types' => ['type' => 'object', 'label' => 'Entity Types'],
    ];
  }

}
