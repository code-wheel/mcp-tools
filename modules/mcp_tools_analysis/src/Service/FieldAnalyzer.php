<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for analyzing field usage.
 */
class FieldAnalyzer {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Find unused fields across all entities.
   *
   * @return array
   *   Results with unused fields.
   */
  public function findUnusedFields(): array {
    try {
      $unusedFields = [];
      $fieldConfigStorage = $this->entityTypeManager->getStorage('field_config');
      $fieldConfigs = $fieldConfigStorage->loadMultiple();

      foreach ($fieldConfigs as $fieldConfig) {
        $entityType = $fieldConfig->getTargetEntityTypeId();
        $bundle = $fieldConfig->getTargetBundle();
        $fieldName = $fieldConfig->getName();

        // Skip base fields.
        if (!str_starts_with($fieldName, 'field_')) {
          continue;
        }

        try {
          $storage = $this->entityTypeManager->getStorage($entityType);
          $query = $storage->getQuery()
            ->condition($fieldName, NULL, 'IS NOT NULL')
            ->accessCheck(FALSE)
            ->range(0, 1);

          if ($entityType === 'node' || $entityType === 'taxonomy_term' || $entityType === 'media') {
            $query->condition('type', $bundle);
          }
          elseif ($entityType === 'paragraph') {
            $query->condition('type', $bundle);
          }

          $count = $query->count()->execute();

          if ($count === 0) {
            $unusedFields[] = [
              'field_name' => $fieldName,
              'entity_type' => $entityType,
              'bundle' => $bundle,
              'field_type' => $fieldConfig->getType(),
              'label' => $fieldConfig->getLabel(),
            ];
          }
        }
        catch (\Exception $e) {
          // Skip fields that can't be queried.
          continue;
        }
      }

      $suggestions = [];
      if (!empty($unusedFields)) {
        $suggestions[] = 'Review unused fields and consider removing them to simplify content editing.';
        $suggestions[] = 'Before deleting, verify the field is not used in views or templates.';
        $suggestions[] = 'Use drush field:delete to remove fields safely.';
      }
      else {
        $suggestions[] = 'All configured fields are in use.';
      }

      return [
        'success' => TRUE,
        'data' => [
          'unused_fields' => $unusedFields,
          'unused_count' => count($unusedFields),
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to find unused fields: ' . $e->getMessage()];
    }
  }

}
