<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_metatag\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_metatag\Service\MetatagService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting metatags from a specific entity.
 *
 * @Tool(
 *   id = "mcp_metatag_get_entity",
 *   label = @Translation("Get Entity Metatags"),
 *   description = @Translation("Get metatags for a specific entity (node, term, user, etc.)."),
 *   category = "metatag",
 * )
 */
class GetEntityMetatags extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    if (empty($entityType) || empty($entityId)) {
      return ['success' => FALSE, 'error' => 'Both entity_type and entity_id are required.'];
    }

    return $this->metatagService->getEntityMetatags($entityType, (int) $entityId);
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
      'stored_tags' => ['type' => 'object', 'label' => 'Stored Metatags'],
      'computed_tags' => ['type' => 'object', 'label' => 'Computed Metatags'],
    ];
  }

}
