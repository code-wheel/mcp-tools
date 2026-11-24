<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_pathauto\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\pathauto\PathautoGeneratorInterface;

/**
 * Service for Pathauto pattern operations.
 */
class PathautoService {

  /**
   * Entities a call of generateAliases() does at most.
   */
  public const GENERATE_SIZE = 50;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PathautoGeneratorInterface $pathautoGenerator,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * List all URL alias patterns.
   *
   * @param string|null $entityType
   *   Optional entity type to filter patterns.
   *
   * @return array
   *   Result array with patterns.
   */
  public function listPatterns(?string $entityType = NULL): array {
    try {
      $storage = $this->entityTypeManager->getStorage('pathauto_pattern');
      $query = $storage->getQuery()->accessCheck(TRUE);

      if ($entityType) {
        $query->condition('type', 'canonical_entities:' . $entityType);
      }

      $ids = $query->execute();
      $patterns = $storage->loadMultiple($ids);

      $result = [];
      foreach ($patterns as $pattern) {
        $result[] = $this->formatPattern($pattern);
      }

      // Sort by weight.
      usort($result, fn($a, $b) => $a['weight'] <=> $b['weight']);

      return [
        'success' => TRUE,
        'data' => [
          'total' => count($result),
          'patterns' => $result,
          'filter' => $entityType ? ['entity_type' => $entityType] : NULL,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to list patterns: ' . $e->getMessage()];
    }
  }

  /**
   * Get details of a specific pattern.
   *
   * @param string $id
   *   The pattern ID.
   *
   * @return array
   *   Result array with pattern details.
   */
  public function getPattern(string $id): array {
    try {
      $pattern = $this->entityTypeManager->getStorage('pathauto_pattern')->load($id);

      if (!$pattern) {
        return ['success' => FALSE, 'error' => "Pattern '$id' not found."];
      }

      return [
        'success' => TRUE,
        'data' => $this->formatPattern($pattern, TRUE),
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to get pattern: ' . $e->getMessage()];
    }
  }

  /**
   * Create a new URL alias pattern.
   *
   * @param string $id
   *   The pattern machine name.
   * @param string $label
   *   The pattern label.
   * @param string $pattern
   *   The URL alias pattern (e.g., "[node:title]").
   * @param string $entityType
   *   The entity type (e.g., "node", "taxonomy_term").
   * @param string|null $bundle
   *   Optional bundle to restrict the pattern to.
   *
   * @return array
   *   Result array.
   */
  public function createPattern(string $id, string $label, string $pattern, string $entityType, ?string $bundle = NULL): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    try {
      $storage = $this->entityTypeManager->getStorage('pathauto_pattern');

      // Check if pattern already exists.
      if ($storage->load($id)) {
        return ['success' => FALSE, 'error' => "Pattern with ID '$id' already exists."];
      }

      // Validate entity type.
      $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityType, FALSE);
      if (!$entityTypeDefinition) {
        return ['success' => FALSE, 'error' => "Invalid entity type '$entityType'."];
      }

      // Create the pattern.
      $patternEntity = $storage->create([
        'id' => $id,
        'label' => $label,
        'type' => 'canonical_entities:' . $entityType,
        'pattern' => $pattern,
        'weight' => 0,
        'status' => TRUE,
      ]);
      if ($bundle && $entityTypeDefinition->getKey('bundle')) {
        $this->setBundleCondition($patternEntity, $entityType, $bundle);
        // Pathauto uses the first pattern that matches, by weight. Its own
        // form gives a pattern for one bundle -5, so that it wins over a
        // pattern for every bundle.
        $patternEntity->setWeight(-5);
      }

      $patternEntity->save();

      $this->auditLogger->logSuccess('create_pattern', 'pathauto_pattern', $id, [
        'label' => $label,
        'pattern' => $pattern,
        'entity_type' => $entityType,
        'bundle' => $bundle,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'label' => $label,
          'pattern' => $pattern,
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'message' => "Pattern '$label' created successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_pattern', 'pathauto_pattern', $id, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to create pattern: ' . $e->getMessage()];
    }
  }

  /**
   * Update an existing URL alias pattern.
   *
   * @param string $id
   *   The pattern ID.
   * @param array $values
   *   Values to update (label, pattern, weight, status, bundle).
   *
   * @return array
   *   Result array.
   */
  public function updatePattern(string $id, array $values): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    try {
      $storage = $this->entityTypeManager->getStorage('pathauto_pattern');
      $pattern = $storage->load($id);

      if (!$pattern) {
        return ['success' => FALSE, 'error' => "Pattern '$id' not found."];
      }

      $updated = [];

      if (isset($values['label'])) {
        $pattern->set('label', $values['label']);
        $updated[] = 'label';
      }

      if (isset($values['pattern'])) {
        $pattern->set('pattern', $values['pattern']);
        $updated[] = 'pattern';
      }

      if (isset($values['weight'])) {
        $pattern->set('weight', (int) $values['weight']);
        $updated[] = 'weight';
      }

      if (isset($values['status'])) {
        $pattern->set('status', (bool) $values['status']);
        $updated[] = 'status';
      }

      if (isset($values['bundle'])) {
        // Get entity type from the pattern type.
        $type = $pattern->get('type');
        $entityType = str_replace('canonical_entities:', '', $type);
        $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityType, FALSE);

        if ($entityTypeDefinition) {
          $bundleKey = $entityTypeDefinition->getKey('bundle');
          if ($bundleKey) {
            $this->setBundleCondition($pattern, $entityType, (string) $values['bundle']);
            $updated[] = 'bundle';
          }
        }
      }

      if (empty($updated)) {
        return [
          'success' => TRUE,
          'data' => [
            'id' => $id,
            'message' => 'No changes were provided.',
          ],
        ];
      }

      $pattern->save();

      $this->auditLogger->logSuccess('update_pattern', 'pathauto_pattern', $id, [
        'updated_fields' => $updated,
        'values' => $values,
      ]);

      return [
        'success' => TRUE,
        'data' => array_merge(
          $this->formatPattern($pattern),
          [
            'updated_fields' => $updated,
            'message' => "Pattern '$id' updated successfully.",
          ]
        ),
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('update_pattern', 'pathauto_pattern', $id, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to update pattern: ' . $e->getMessage()];
    }
  }

  /**
   * Delete a URL alias pattern.
   *
   * @param string $id
   *   The pattern ID.
   *
   * @return array
   *   Result array.
   */
  public function deletePattern(string $id): array {
    if (!$this->accessManager->canWrite('delete')) {
      return $this->accessManager->getWriteAccessDenied();
    }

    try {
      $storage = $this->entityTypeManager->getStorage('pathauto_pattern');
      $pattern = $storage->load($id);

      if (!$pattern) {
        return ['success' => FALSE, 'error' => "Pattern '$id' not found."];
      }

      $label = $pattern->label();
      $pattern->delete();

      $this->auditLogger->logSuccess('delete_pattern', 'pathauto_pattern', $id, [
        'label' => $label,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'label' => $label,
          'message' => "Pattern '$label' deleted successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_pattern', 'pathauto_pattern', $id, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to delete pattern: ' . $e->getMessage()];
    }
  }

  /**
   * Bulk generate URL aliases for entities.
   *
   * @param string $entityType
   *   The entity type (e.g., "node", "taxonomy_term").
   * @param string|null $bundle
   *   Optional bundle to limit generation.
   * @param bool $update
   *   Whether to update existing aliases (default: FALSE, only create missing).
   * @param int $after
   *   Start after this entity id (the next_after of the last call).
   * @param int $size
   *   Entities to do in this call, at most GENERATE_SIZE.
   *
   * @return array
   *   Result array.
   */
  public function generateAliases(string $entityType, ?string $bundle = NULL, bool $update = FALSE, int $after = 0, int $size = self::GENERATE_SIZE): array {
    $size = max(1, min($size, self::GENERATE_SIZE));
    try {
      // Validate entity type.
      $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityType, FALSE);
      if (!$entityTypeDefinition) {
        return ['success' => FALSE, 'error' => "Invalid entity type '$entityType'."];
      }
      $idKey = (string) $entityTypeDefinition->getKey('id');
      $storage = $this->entityTypeManager->getStorage($entityType);
      // In id order from a cursor: the first 500 in no order were done
      // every time, and an entity past them never got its alias.
      $query = fn() => $storage->getQuery()->accessCheck(TRUE)->condition($idKey, $after, '>');
      $part = $query();
      if ($bundle && ($bundleKey = $entityTypeDefinition->getKey('bundle'))) {
        $part->condition($bundleKey, $bundle);
      }
      $ids = array_values($part->sort($idKey)->range(0, $size)->execute());
      if (!$this->accessManager->canWrite('write', max(1, count($ids)))) {
        return $this->accessManager->getWriteAccessDenied();
      }

      $created = 0;
      $updated = 0;
      $skipped = 0;
      foreach ($ids === [] ? [] : $storage->loadMultiple($ids) as $entity) {
        $result = $this->pathautoGenerator->updateEntityAlias($entity, $update ? 'update' : 'insert');
        if ($result) {
          $update ? $updated++ : $created++;
        }
        else {
          $skipped++;
        }
      }

      $next = $ids === [] ? $after : (int) end($ids);
      $rest = $query()->condition($idKey, $next, '>');
      if ($bundle && ($bundleKey = $entityTypeDefinition->getKey('bundle'))) {
        $rest->condition($bundleKey, $bundle);
      }
      $remaining = (int) $rest->count()->execute();

      $this->auditLogger->logSuccess('generate_aliases', $entityType, $bundle ?? 'all', [
        'processed' => count($ids),
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'update_mode' => $update,
        'after' => $after,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'processed' => count($ids),
          'created' => $created,
          'updated' => $updated,
          'skipped' => $skipped,
          'next_after' => $next,
          'remaining' => $remaining,
          'done' => $remaining === 0,
          'message' => sprintf(
            'Processed %d entities: %d aliases %s, %d skipped. %s',
            count($ids),
            $update ? $updated : $created,
            $update ? 'updated' : 'created',
            $skipped,
            $remaining === 0 ? 'All done.' : "$remaining are left: call again with after: $next."
          ) . ($ids === [] && $after === 0 ? ' No entities found to process.' : ''),
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('generate_aliases', $entityType, $bundle ?? 'all', ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to generate aliases: ' . $e->getMessage()];
    }
  }

  /**
   * Limits a pattern to one bundle, or to none.
   *
   * Pathauto keeps a list of condition plugins, one of them for bundles. It
   * is not a map of bundle key to bundles: a pattern saved in that shape
   * fails with "Plugin ID ... was not found".
   *
   * @param object $pattern
   *   The pathauto pattern.
   * @param string $entityType
   *   The entity type the pattern is for.
   * @param string $bundle
   *   The bundle, or an empty string for every bundle.
   */
  protected function setBundleCondition(object $pattern, string $entityType, string $bundle): void {
    foreach ($pattern->getSelectionConditions() as $conditionId => $condition) {
      if (in_array($condition->getPluginId(), ['entity_bundle:' . $entityType, 'node_type'], TRUE)) {
        $pattern->removeSelectionCondition($conditionId);
      }
    }
    if ($bundle !== '') {
      $pattern->addSelectionCondition([
        'id' => 'entity_bundle:' . $entityType,
        'bundles' => [$bundle => $bundle],
        'negate' => FALSE,
        'context_mapping' => [$entityType => $entityType],
      ]);
    }
  }

  /**
   * Format a pattern entity for output.
   *
   * @param object $pattern
   *   The pattern entity.
   * @param bool $includeSelectionDetails
   *   Whether to include detailed selection criteria.
   *
   * @return array
   *   Formatted pattern data.
   */
  protected function formatPattern($pattern, bool $includeSelectionDetails = FALSE): array {
    $type = $pattern->get('type');
    $entityType = str_replace('canonical_entities:', '', $type);

    $selectionCriteria = $pattern->get('selection_criteria') ?? [];
    $bundles = [];

    // Each entry is the configuration of one condition plugin. The bundle
    // condition lists its bundles under "bundles".
    foreach ($selectionCriteria as $condition) {
      if (is_array($condition) && is_array($condition['bundles'] ?? NULL)) {
        $bundles = array_values(array_unique(array_merge($bundles, array_keys($condition['bundles']))));
      }
    }

    $data = [
      'id' => $pattern->id(),
      'label' => $pattern->label(),
      'pattern' => $pattern->get('pattern'),
      'entity_type' => $entityType,
      'bundles' => $bundles,
      'weight' => (int) $pattern->get('weight'),
      'status' => (bool) $pattern->get('status'),
    ];

    if ($includeSelectionDetails) {
      $data['selection_criteria'] = $selectionCriteria;
      $data['type'] = $type;
    }

    return $data;
  }

}
