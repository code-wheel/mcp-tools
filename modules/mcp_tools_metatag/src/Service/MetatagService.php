<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_metatag\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\metatag\MetatagManagerInterface;
use Drupal\metatag\MetatagTagPluginManager;
use Drupal\metatag\MetatagGroupPluginManager;
use Drupal\mcp_tools\Service\LatestRevision;
use Drupal\mcp_tools\Service\EntityAccess;
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
      $entity = LatestRevision::load($this->entityTypeManager, $entityType, $entityId);
      if ($entity && ($denied = EntityAccess::denied($entity, 'view')) !== NULL) {
        return $denied;
      }

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
            'message' => 'Entity does not have a metatag field configured.',
          ],
        ];
      }

      // Get the metatag values from the field.
      $metatagValues = $this->storedTags($entity, $metatagFieldName);

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
      $entity = LatestRevision::load($this->entityTypeManager, $entityType, $entityId);
      if ($entity && ($denied = EntityAccess::denied($entity, 'update')) !== NULL) {
        return $denied;
      }

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
      $existingValues = $this->storedTags($entity, $metatagFieldName);

      $newValues = array_merge($existingValues, $tags);

      // Remove empty values.
      $newValues = array_filter($newValues, function ($value) {
        return $value !== '' && $value !== NULL;
      });

      // Set the metatag field. Its tags live serialized in a single `value`
      // column, so they have to be encoded rather than assigned as properties.
      $entity->set($metatagFieldName, ['value' => metatag_data_encode($newValues)]);

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
   * Lists the metatag tags, all or one group's, each described once.
   *
   * A tag gives its id, label, a plain description when it has one, group,
   * type, and secure or multiple only when set. by_group names its tags by
   * id, in weight order.
   *
   * @param string|null $group
   *   Only this group's tags (a group id, for example "open_graph").
   *
   * @return array
   *   The tags, or an error for an unknown group.
   */
  public function listAvailableTags(?string $group = NULL): array {
    $definitions = $this->tagPluginManager->getDefinitions();
    $groupDefinitions = $this->groupPluginManager->getDefinitions();
    $plain = static fn(mixed $text): string => trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5));

    // "all" is what a model sends for no filter.
    $group = $group === NULL || in_array(strtolower(trim($group)), ['', 'all'], TRUE) ? NULL : trim($group);
    if ($group !== NULL) {
      $groupOf = static fn(array $definition): string => (string) ($definition['group'] ?? 'basic');
      $groups = array_unique(array_map($groupOf, $definitions));
      sort($groups);
      if (!in_array($group, $groups, TRUE)) {
        $error = "No metatag group '$group'. Groups with tags: " . implode(', ', $groups) . '.';
        return ['success' => FALSE, 'error' => $error];
      }
    }

    $tags = [];
    $byGroup = [];
    $weights = [];
    foreach ($definitions as $id => $definition) {
      $groupId = (string) ($definition['group'] ?? 'basic');
      if ($group !== NULL && $groupId !== $group) {
        continue;
      }
      $tagInfo = ['id' => (string) $id, 'label' => (string) $definition['label']];
      $description = $plain($definition['description'] ?? '');
      if ($description !== '') {
        $tagInfo['description'] = $description;
      }
      $tagInfo += ['group' => $groupId, 'type' => $definition['type'] ?? 'string'];
      foreach (['secure', 'multiple'] as $flag) {
        if (!empty($definition[$flag])) {
          $tagInfo[$flag] = TRUE;
        }
      }
      $tags[] = $tagInfo;
      $byGroup[$groupId] ??= [
        'group_id' => $groupId,
        'group_label' => isset($groupDefinitions[$groupId]) ? (string) $groupDefinitions[$groupId]['label'] : $groupId,
        'tags' => [],
      ];
      $byGroup[$groupId]['tags'][] = (string) $id;
      $weights[$id] = $definition['weight'] ?? 0;
    }

    foreach ($byGroup as &$entry) {
      usort($entry['tags'], static fn(string $a, string $b): int => $weights[$a] <=> $weights[$b]);
    }
    unset($entry);

    $data = [
      'total' => count($definitions),
      'by_group' => array_values($byGroup),
      'all_tags' => $tags,
    ];
    if ($group !== NULL) {
      $data['matching'] = count($tags);
    }
    return ['success' => TRUE, 'data' => $data];
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
    // Match on the field type, not on a field name: the Metatag module gives
    // every entity type with a canonical URL a read-only computed field called
    // `metatag`, which is never the one to write to.
    foreach ($entity->getFieldDefinitions() as $fieldName => $definition) {
      if ($definition->getType() === 'metatag') {
        return $fieldName;
      }
    }

    return NULL;
  }

  /**
   * Get the meta tags an entity overrides, decoded.
   *
   * @param object $entity
   *   The entity to read.
   * @param string $fieldName
   *   The name of its metatag field.
   *
   * @return array
   *   The stored tags, keyed by tag name. Empty if the entity overrides none.
   */
  protected function storedTags($entity, string $fieldName): array {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return [];
    }

    $value = $entity->get($fieldName)->first()->get('value')->getValue();

    return is_string($value) ? metatag_data_decode($value) : (array) $value;
  }

}
