<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_moderation\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_moderation\Service\ModerationService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing all content moderation workflows.
 *
 * @Tool(
 *   id = "mcp_moderation_get_workflows",
 *   label = @Translation("Get Workflows"),
 *   description = @Translation("List all content moderation workflows with their states and transitions."),
 *   category = "moderation",
 * )
 */
class GetWorkflows extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return $this->moderationService->getWorkflows();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total' => ['type' => 'integer', 'label' => 'Total Workflows'],
      'workflows' => ['type' => 'list', 'label' => 'Workflows'],
    ];
  }

}
