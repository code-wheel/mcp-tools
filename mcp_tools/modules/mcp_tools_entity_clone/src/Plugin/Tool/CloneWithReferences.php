<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_entity_clone\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_entity_clone\Service\EntityCloneService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for cloning an entity with its referenced entities.
 *
 * @Tool(
 *   id = "mcp_entity_clone_with_refs",
 *   label = @Translation("Clone Entity with References"),
 *   description = @Translation("Clone an entity and specified referenced entities, updating references in the clone."),
 *   category = "entity_clone",
 * )
 */
class CloneWithReferences extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    $referenceFields = $input['reference_fields'] ?? [];
    if (!is_array($referenceFields)) {
      $referenceFields = [$referenceFields];
    }

    return $this->entityCloneService->cloneWithReferences($entityType, $entityId, $referenceFields);
  }

  public function getInputDefinition(): array {
    return [
      'entity_type' => [
        'type' => 'string',
        'label' => 'Entity Type',
        'description' => 'The entity type to clone (e.g., node, media)',
        'required' => TRUE,
      ],
      'entity_id' => [
        'type' => 'string',
        'label' => 'Entity ID',
        'description' => 'The ID of the entity to clone',
        'required' => TRUE,
      ],
      'reference_fields' => [
        'type' => 'array',
        'label' => 'Reference Fields',
        'description' => 'List of reference field names to also clone (e.g., ["field_related_articles", "field_author"])',
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
      'cloned_references' => ['type' => 'array', 'label' => 'Cloned Referenced Entities'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
