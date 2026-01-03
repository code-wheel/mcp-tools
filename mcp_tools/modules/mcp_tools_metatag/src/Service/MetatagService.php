<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_metatag\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\metatag\MetatagManagerInterface;
use Drupal\metatag\MetatagTagPluginManager;
use Drupal\metatag\MetatagGroupPluginManager;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for metatag operations.
 */
class MetatagService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MetatagTagPluginManager $tagPluginManager,
    protected MetatagGroupPluginManager $groupPluginManager,
    protected MetatagManagerInterface $metatagManager,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Get default metatag configuration.
   *
   * @param string|null $type
   *   Optional entity type to filter defaults (e.g., 'node', 'taxonomy_term').
   *
   * @return array
   *   Default metatag configuration.
   */
  public function getMetatagDefaults(?string $type = NULL): array {
    $storage = $this->entityTypeManager->getStorage('metatag_defaults');

    if ($type) {
      // Load specific entity type defaults.
      $defaults = $storage->load($type);
      if (!$defaults) {
        // Try loading with node__ prefix for content types.
        $defaults = $storage->load('node__' . $type);
      }

      if (!$defaults) {
        return [
          'success' => TRUE,
          'data' => [
            'type' => $type,
            'defaults' => [],
            'message' => "No metatag defaults found for type '$type'. Global defaults may apply.",
          ],
        ];
      }

      return [
        'success' => TRUE,
        'data' => [
          'id' => $defaults->id(),
          'label' => $defaults->label(),
          'tags' => $defaults->get('tags') ?: [],
        ],
      ];
    }

    // Load all defaults.
    $allDefaults = $storage->loadMultiple();
    $result = [];

    foreach ($allDefaults as $default) {
      $result[] = [
        'id' => $default->id(),
        'label' => $default->label(),
        'tags' => $default->get('tags') ?: [],
      ];
    }

    return [
      'success' => TRUE,
      'data' => [
        'total' => count($result),
        'defaults' => $result,
      ],
    ];
  }

  /**
   * Get metatags for a specific entity.
   *
   * @param string $entityType
   *   The entity type (e.g., 'node', 'taxonomy_term', 'user').
   * @param int $entityId
   *   The entity ID.
   *
   * @return array
   *   Entity metatags.
   */
  public function getEntityMetatags(string $entityType, int $entityId): array {
    try {
      $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);

      if (!$entity) {
        return ['success' => FALSE, 'error' => "Entity '$entityType' with ID $entityId not found."];
      }

      // Check if entity has metatag field.
      $metatagFieldName = $this->findMetatagField($entity);

      if (!$metatagFieldName) {
        return [
          'success' => TRUE,
          'data' => [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_label' => $entity->label(),
            'has_metatag_field' => FALSE,
            'tags' => [],
            'message' => 'Entity does not have a metatag field configured.',
          ],
        ];
      }

      // Get the metatag values from the field.
      $metatagValues = [];
      if ($entity->hasField($metatagFieldName) && !$entity->get($metatagFieldName)->isEmpty()) {
        $metatagValues = $entity->get($metatagFieldName)->first()->getValue();
      }

      // Get computed metatags (including defaults and tokens).
      $computedTags = $this->metatagManager->tagsFromEntityWithDefaults($entity);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'entity_id' => $entityId,
          'entity_label' => $entity->label(),
          'has_metatag_field' => TRUE,
          'metatag_field' => $metatagFieldName,
          'stored_tags' => $metatagValues,
          'computed_tags' => $computedTags,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to get entity metatags: ' . $e->getMessage()];
    }
  }

  /**
   * Set metatags on an entity.
   *
   * @param string $entityType
   *   The entity type (e.g., 'node', 'taxonomy_term', 'user').
   * @param int $entityId
   *   The entity ID.
   * @param array $tags
   *   The metatags to set.
   *
   * @return array
   *   Result of the operation.
   */
  public function setEntityMetatags(string $entityType, int $entityId, array $tags): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    try {
      $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);

      if (!$entity) {
        return ['success' => FALSE, 'error' => "Entity '$entityType' with ID $entityId not found."];
      }

      // Check if entity has metatag field.
      $metatagFieldName = $this->findMetatagField($entity);

      if (!$metatagFieldName) {
        return [
          'success' => FALSE,
          'error' => "Entity does not have a metatag field. Add a metatag field to this entity type first.",
        ];
      }

      // Validate tags.
      $availableTags = array_keys($this->tagPluginManager->getDefinitions());
      $invalidTags = array_diff(array_keys($tags), $availableTags);
      if (!empty($invalidTags)) {
        return [
          'success' => FALSE,
          'error' => 'Invalid metatag names: ' . implode(', ', $invalidTags),
          'available_tags' => $availableTags,
        ];
      }

      // Get existing values and merge with new ones.
      $existingValues = [];
      if (!$entity->get($metatagFieldName)->isEmpty()) {
        $existingValues = $entity->get($metatagFieldName)->first()->getValue();
      }

      $newValues = array_merge($existingValues, $tags);

      // Remove empty values.
      $newValues = array_filter($newValues, function ($value) {
        return $value !== '' && $value !== NULL;
      });

      // Set the metatag field.
      $entity->set($metatagFieldName, $newValues);

      // Save with a revision if supported.
      if (method_exists($entity, 'setNewRevision')) {
        $entity->setNewRevision(TRUE);
        if (method_exists($entity, 'setRevisionLogMessage')) {
          $entity->setRevisionLogMessage('Metatags updated via MCP Tools');
        }
      }

      $entity->save();

      $this->auditLogger->logSuccess('set_metatags', $entityType, (string) $entityId, [
        'tags_set' => array_keys($tags),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'entity_id' => $entityId,
          'entity_label' => $entity->label(),
          'tags_updated' => array_keys($tags),
          'current_tags' => $newValues,
          'message' => 'Metatags updated successfully.',
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('set_metatags', $entityType, (string) $entityId, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to set entity metatags: ' . $e->getMessage()];
    }
  }

  /**
   * List available metatag groups.
   *
   * @return array
   *   List of metatag groups.
   */
  public function listMetatagGroups(): array {
    $definitions = $this->groupPluginManager->getDefinitions();

    $groups = [];
    foreach ($definitions as $id => $definition) {
      $groups[] = [
        'id' => $id,
        'label' => (string) $definition['label'],
        'description' => isset($definition['description']) ? (string) $definition['description'] : NULL,
        'weight' => $definition['weight'] ?? 0,
      ];
    }

    // Sort by weight.
    usort($groups, fn($a, $b) => $a['weight'] <=> $b['weight']);

    return [
      'success' => TRUE,
      'data' => [
        'total' => count($groups),
        'groups' => $groups,
      ],
    ];
  }

  /**
   * List all available metatag tags with descriptions.
   *
   * @return array
   *   List of available tags.
   */
  public function listAvailableTags(): array {
    $definitions = $this->tagPluginManager->getDefinitions();
    $groupDefinitions = $this->groupPluginManager->getDefinitions();

    $tags = [];
    $byGroup = [];

    foreach ($definitions as $id => $definition) {
      $groupId = $definition['group'] ?? 'basic';
      $groupLabel = isset($groupDefinitions[$groupId]) ? (string) $groupDefinitions[$groupId]['label'] : $groupId;

      $tagInfo = [
        'id' => $id,
        'label' => (string) $definition['label'],
        'description' => isset($definition['description']) ? (string) $definition['description'] : NULL,
        'group' => $groupId,
        'group_label' => $groupLabel,
        'weight' => $definition['weight'] ?? 0,
        'type' => $definition['type'] ?? 'string',
        'secure' => $definition['secure'] ?? FALSE,
        'multiple' => $definition['multiple'] ?? FALSE,
      ];

      $tags[] = $tagInfo;

      if (!isset($byGroup[$groupId])) {
        $byGroup[$groupId] = [
          'group_id' => $groupId,
          'group_label' => $groupLabel,
          'tags' => [],
        ];
      }
      $byGroup[$groupId]['tags'][] = $tagInfo;
    }

    // Sort tags by weight within each group.
    foreach ($byGroup as &$group) {
      usort($group['tags'], fn($a, $b) => $a['weight'] <=> $b['weight']);
    }

    return [
      'success' => TRUE,
      'data' => [
        'total' => count($tags),
        'by_group' => array_values($byGroup),
        'all_tags' => $tags,
      ],
    ];
  }

  /**
   * Find the metatag field on an entity.
   *
   * @param object $entity
   *   The entity to check.
   *
   * @return string|null
   *   The metatag field name or NULL if not found.
   */
  protected function findMetatagField($entity): ?string {
    // Common metatag field names.
    $possibleFields = ['field_metatag', 'field_meta_tags', 'metatag'];

    foreach ($possibleFields as $fieldName) {
      if ($entity->hasField($fieldName)) {
        return $fieldName;
      }
    }

    // Check all fields for metatag type.
    foreach ($entity->getFieldDefinitions() as $fieldName => $definition) {
      if ($definition->getType() === 'metatag') {
        return $fieldName;
      }
    }

    return NULL;
  }

}
