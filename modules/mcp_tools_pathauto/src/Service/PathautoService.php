<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_pathauto\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\pathauto\AliasCleanerInterface;
use Drupal\pathauto\PathautoGeneratorInterface;

/**
 * Service for Pathauto pattern operations.
 */
class PathautoService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PathautoGeneratorInterface $pathautoGenerator,
    protected AliasCleanerInterface $aliasCleaner,
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

      // Build selection criteria.
      $selectionCriteria = [];
      if ($bundle) {
        $bundleKey = $entityTypeDefinition->getKey('bundle');
        if ($bundleKey) {
          $selectionCriteria[$bundleKey] = [$bundle => $bundle];
        }
      }

      // Create the pattern.
      $patternEntity = $storage->create([
        'id' => $id,
        'label' => $label,
        'type' => 'canonical_entities:' . $entityType,
        'pattern' => $pattern,
        'selection_criteria' => $selectionCriteria,
        'weight' => 0,
        'status' => TRUE,
      ]);

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
            $bundle = $values['bundle'];
            $selectionCriteria = $bundle ? [$bundleKey => [$bundle => $bundle]] : [];
            $pattern->set('selection_criteria', $selectionCriteria);
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
   *
   * @return array
   *   Result array.
   */
  public function generateAliases(string $entityType, ?string $bundle = NULL, bool $update = FALSE): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    try {
      // Validate entity type.
      $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityType, FALSE);
      if (!$entityTypeDefinition) {
        return ['success' => FALSE, 'error' => "Invalid entity type '$entityType'."];
      }

      $storage = $this->entityTypeManager->getStorage($entityType);
      $query = $storage->getQuery()->accessCheck(TRUE);

      if ($bundle) {
        $bundleKey = $entityTypeDefinition->getKey('bundle');
        if ($bundleKey) {
          $query->condition($bundleKey, $bundle);
        }
      }

      // Limit to prevent timeout.
      $query->range(0, 500);
      $ids = $query->execute();

      if (empty($ids)) {
        return [
          'success' => TRUE,
          'data' => [
            'entity_type' => $entityType,
            'bundle' => $bundle,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'message' => 'No entities found to process.',
          ],
        ];
      }

      $entities = $storage->loadMultiple($ids);

      $created = 0;
      $updated = 0;
      $skipped = 0;

      foreach ($entities as $entity) {
        $op = $update ? 'update' : 'insert';
        $result = $this->pathautoGenerator->updateEntityAlias($entity, $op);

        if ($result) {
          if ($update) {
            $updated++;
          }
          else {
            $created++;
          }
        }
        else {
          $skipped++;
        }
      }

      $this->auditLogger->logSuccess('generate_aliases', $entityType, $bundle ?? 'all', [
        'processed' => count($entities),
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'update_mode' => $update,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'processed' => count($entities),
          'created' => $created,
          'updated' => $updated,
          'skipped' => $skipped,
          'message' => sprintf(
            'Processed %d entities: %d aliases %s, %d skipped.',
            count($entities),
            $update ? $updated : $created,
            $update ? 'updated' : 'created',
            $skipped
          ),
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('generate_aliases', $entityType, $bundle ?? 'all', ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to generate aliases: ' . $e->getMessage()];
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

    // Extract bundle from selection criteria.
    if (!empty($selectionCriteria)) {
      foreach ($selectionCriteria as $key => $value) {
        if (is_array($value)) {
          $bundles = array_keys($value);
          break;
        }
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
