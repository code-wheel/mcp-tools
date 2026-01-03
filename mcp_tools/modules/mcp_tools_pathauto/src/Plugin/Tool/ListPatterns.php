<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_pathauto\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_pathauto\Service\PathautoService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing URL alias patterns.
 *
 * @Tool(
 *   id = "mcp_pathauto_list_patterns",
 *   label = @Translation("List Pathauto Patterns"),
 *   description = @Translation("List all URL alias patterns configured in Pathauto."),
 *   category = "pathauto",
 * )
 */
class ListPatterns extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected PathautoService $pathautoService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->pathautoService = $container->get('mcp_tools_pathauto.pathauto');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $entityType = $input['entity_type'] ?? NULL;

    return $this->pathautoService->listPatterns($entityType);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'entity_type' => [
        'type' => 'string',
        'label' => 'Entity Type',
        'description' => 'Optional entity type to filter patterns (e.g., "node", "taxonomy_term", "user").',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total' => ['type' => 'integer', 'label' => 'Total Patterns'],
      'patterns' => ['type' => 'array', 'label' => 'Pattern List'],
      'filter' => ['type' => 'object', 'label' => 'Applied Filter'],
    ];
  }

}
