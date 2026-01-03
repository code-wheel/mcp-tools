<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_entity_clone\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_entity_clone\Service\EntityCloneService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for cloning a single entity.
 *
 * @Tool(
 *   id = "mcp_entity_clone_clone",
 *   label = @Translation("Clone Entity"),
 *   description = @Translation("Clone a single entity with optional title prefix/suffix and child paragraph handling."),
 *   category = "entity_clone",
 * )
 */
class CloneEntity extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected EntityCloneService $entityCloneService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityCloneService = $container->get('mcp_tools_entity_clone.entity_clone');
    return $instance;
  }

  public function execute(array $input = []): array {
    $entityType = $input['entity_type'] ?? '';
    $entityId = $input['entity_id'] ?? '';

    if (empty($entityType) || empty($entityId)) {
      return ['success' => FALSE, 'error' => 'Both entity_type and entity_id are required.'];
    }

    $options = [];
    if (isset($input['title_prefix'])) {
      $options['title_prefix'] = $input['title_prefix'];
    }
    if (isset($input['title_suffix'])) {
      $options['title_suffix'] = $input['title_suffix'];
    }
    if (isset($input['clone_children'])) {
      $options['clone_children'] = (bool) $input['clone_children'];
    }

    return $this->entityCloneService->cloneEntity($entityType, $entityId, $options);
  }

  public function getInputDefinition(): array {
    return [
      'entity_type' => [
        'type' => 'string',
        'label' => 'Entity Type',
        'description' => 'The entity type to clone (e.g., node, media, paragraph)',
        'required' => TRUE,
      ],
      'entity_id' => [
        'type' => 'string',
        'label' => 'Entity ID',
        'description' => 'The ID of the entity to clone',
        'required' => TRUE,
      ],
      'title_prefix' => [
        'type' => 'string',
        'label' => 'Title Prefix',
        'description' => 'Prefix to add to the cloned entity title',
        'required' => FALSE,
      ],
      'title_suffix' => [
        'type' => 'string',
        'label' => 'Title Suffix',
        'description' => 'Suffix to add to the cloned entity title (default: " (Clone)")',
        'required' => FALSE,
      ],
      'clone_children' => [
        'type' => 'boolean',
        'label' => 'Clone Children',
        'description' => 'Whether to clone child paragraphs (default: true)',
        'required' => FALSE,
      ],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type'],
      'source_id' => ['type' => 'string', 'label' => 'Source Entity ID'],
      'clone_id' => ['type' => 'string', 'label' => 'Cloned Entity ID'],
      'clone_uuid' => ['type' => 'string', 'label' => 'Cloned Entity UUID'],
      'label' => ['type' => 'string', 'label' => 'Cloned Entity Label'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
