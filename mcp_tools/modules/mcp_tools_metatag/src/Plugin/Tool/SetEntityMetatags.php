<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_metatag\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_metatag\Service\MetatagService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for setting metatags on an entity.
 *
 * @Tool(
 *   id = "mcp_metatag_set_entity",
 *   label = @Translation("Set Entity Metatags"),
 *   description = @Translation("Set metatags on a specific entity (node, term, user, etc.). This is a write operation."),
 *   category = "metatag",
 * )
 */
class SetEntityMetatags extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected MetatagService $metatagService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->metatagService = $container->get('mcp_tools_metatag.metatag');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $entityType = $input['entity_type'] ?? '';
    $entityId = $input['entity_id'] ?? 0;
    $tags = $input['tags'] ?? [];

    if (empty($entityType) || empty($entityId)) {
      return ['success' => FALSE, 'error' => 'Both entity_type and entity_id are required.'];
    }

    if (empty($tags)) {
      return ['success' => FALSE, 'error' => 'At least one metatag must be provided in the tags parameter.'];
    }

    return $this->metatagService->setEntityMetatags($entityType, (int) $entityId, $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'entity_type' => [
        'type' => 'string',
        'label' => 'Entity Type',
        'description' => 'The entity type (e.g., "node", "taxonomy_term", "user").',
        'required' => TRUE,
      ],
      'entity_id' => [
        'type' => 'integer',
        'label' => 'Entity ID',
        'description' => 'The entity ID.',
        'required' => TRUE,
      ],
      'tags' => [
        'type' => 'object',
        'label' => 'Metatags',
        'description' => 'Object of metatag key-value pairs (e.g., {"title": "My Page", "description": "Page description"}).',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type'],
      'entity_id' => ['type' => 'integer', 'label' => 'Entity ID'],
      'entity_label' => ['type' => 'string', 'label' => 'Entity Label'],
      'tags_updated' => ['type' => 'array', 'label' => 'Tags Updated'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
