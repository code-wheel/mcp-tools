<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for managing taxonomies (vocabularies and terms).
 *
 * This service handles write operations for taxonomy entities.
 * For read-only operations, see \Drupal\mcp_tools\Service\TaxonomyService.
 */
class TaxonomyManagementService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
    protected TimeInterface $time,
  ) {}

  /**
   * List all vocabularies.
   *
   * @return array
   *   Result with list of vocabularies.
   */
  public function listVocabularies(): array {
    try {
      $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
      $result = [];

      foreach ($vocabularies as $vocabulary) {
        // Count terms in this vocabulary.
        $termCount = $this->entityTypeManager->getStorage('taxonomy_term')
          ->getQuery()
          ->accessCheck(FALSE)
          ->condition('vid', $vocabulary->id())
          ->count()
          ->execute();

        $result[] = [
          'id' => $vocabulary->id(),
          'label' => $vocabulary->label(),
          'description' => $vocabulary->getDescription() ?: '',
          'term_count' => (int) $termCount,
        ];
      }

      // Sort by label.
      usort($result, fn($a, $b) => strcasecmp($a['label'], $b['label']));

      return [
        'success' => TRUE,
        'data' => [
          'vocabularies' => $result,
          'total' => count($result),
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to list vocabularies: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Get vocabulary details with terms.
   *
   * @param string $id
   *   Vocabulary machine name.
   * @param int $limit
   *   Maximum terms to return (0 for all).
   *
   * @return array
   *   Result with vocabulary details and terms.
   */
  public function getVocabulary(string $id, int $limit = 100): array {
    try {
      $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($id);

      if (!$vocabulary) {
        return [
          'success' => FALSE,
          'error' => "Vocabulary '$id' not found. Use mcp_structure_list_vocabularies to see available vocabularies.",
        ];
      }

      // Get terms with hierarchy info.
      $query = $this->entityTypeManager->getStorage('taxonomy_term')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('vid', $id)
        ->sort('weight')
        ->sort('name');

      if ($limit > 0) {
        $query->range(0, $limit);
      }

      $termIds = $query->execute();
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($termIds);

      $termData = [];
      foreach ($terms as $term) {
        $parents = $this->entityTypeManager->getStorage('taxonomy_term')->loadParents($term->id());
        $parentIds = array_keys($parents);

        $termData[] = [
          'tid' => (int) $term->id(),
          'name' => $term->getName(),
          'description' => $term->getDescription() ?: '',
          'weight' => (int) $term->getWeight(),
          'parent' => !empty($parentIds) ? (int) reset($parentIds) : 0,
        ];
      }

      // Get total term count.
      $totalTerms = $this->entityTypeManager->getStorage('taxonomy_term')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('vid', $id)
        ->count()
        ->execute();

      return [
        'success' => TRUE,
        'data' => [
          'id' => $vocabulary->id(),
          'label' => $vocabulary->label(),
          'description' => $vocabulary->getDescription() ?: '',
          'terms' => $termData,
          'terms_returned' => count($termData),
          'total_terms' => (int) $totalTerms,
          'admin_path' => "/admin/structure/taxonomy/manage/$id/overview",
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to get vocabulary: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Create a new vocabulary.
   *
   * @param string $id
   *   Machine name.
   * @param string $label
   *   Human-readable name.
   * @param string $description
   *   Optional description.
   *
   * @return array
   *   Result with success status.
   */
  public function createVocabulary(string $id, string $label, string $description = ''): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Validate machine name.
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $id)) {
      return [
        'success' => FALSE,
        'error' => 'Invalid machine name. Use lowercase letters, numbers, and underscores. Must start with a letter.',
      ];
    }

    if (strlen($id) > 32) {
      return [
        'success' => FALSE,
        'error' => 'Machine name must be 32 characters or less.',
      ];
    }

    // Check if vocabulary exists.
    $existing = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($id);
    if ($existing) {
      return [
        'success' => FALSE,
        'error' => "Vocabulary '$id' already exists.",
      ];
    }

    try {
      $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->create([
        'vid' => $id,
        'name' => $label,
        'description' => $description,
      ]);
      $vocabulary->save();

      $this->auditLogger->logSuccess('create_vocabulary', 'taxonomy_vocabulary', $id, [
        'label' => $label,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'label' => $label,
          'message' => "Vocabulary '$label' ($id) created successfully.",
          'admin_path' => "/admin/structure/taxonomy/manage/$id/overview",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_vocabulary', 'taxonomy_vocabulary', $id, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to create vocabulary: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Create a taxonomy term.
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   * @param string $name
   *   Term name.
   * @param array $options
   *   Optional: description, parent (term ID), weight.
   *
   * @return array
   *   Result with success status.
   */
  public function createTerm(string $vocabulary, string $name, array $options = []): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Verify vocabulary exists.
    $vocab = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($vocabulary);
    if (!$vocab) {
      return [
        'success' => FALSE,
        'error' => "Vocabulary '$vocabulary' not found.",
      ];
    }

    // Check for duplicate term name in same vocabulary.
    // SECURITY NOTE: accessCheck(FALSE) is intentional here.
    // This is a system-level duplicate check query. We need to check
    // ALL terms regardless of access permissions to prevent duplicates.
    $existing = $this->entityTypeManager->getStorage('taxonomy_term')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', $vocabulary)
      ->condition('name', $name)
      ->execute();

    if (!empty($existing)) {
      return [
        'success' => FALSE,
        'error' => "Term '$name' already exists in vocabulary '$vocabulary'.",
        'existing_tid' => reset($existing),
      ];
    }

    try {
      $termData = [
        'vid' => $vocabulary,
        'name' => $name,
        'description' => [
          'value' => $options['description'] ?? '',
          'format' => 'basic_html',
        ],
        'weight' => $options['weight'] ?? 0,
      ];

      // Handle parent term.
      if (isset($options['parent'])) {
        $termData['parent'] = ['target_id' => $options['parent']];
      }

      // Use getCurrentTime() to avoid frozen REQUEST_TIME in server mode.
      $now = $this->time->getCurrentTime();
      $termData['changed'] = $now;
      $termData['created'] = $now;
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->create($termData);
      $term->save();

      $this->auditLogger->logSuccess('create_term', 'taxonomy_term', (string) $term->id(), [
        'name' => $name,
        'vocabulary' => $vocabulary,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'tid' => $term->id(),
          'name' => $name,
          'vocabulary' => $vocabulary,
          'message' => "Term '$name' created in vocabulary '$vocabulary'.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_term', 'taxonomy_term', 'new', [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to create term: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Create multiple terms at once.
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   * @param array $terms
   *   Array of term names or ['name' => ..., 'parent' => ...] arrays.
   *
   * @return array
   *   Result with success status and created terms.
   */
  public function createTerms(string $vocabulary, array $terms): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Verify vocabulary exists.
    $vocab = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($vocabulary);
    if (!$vocab) {
      return [
        'success' => FALSE,
        'error' => "Vocabulary '$vocabulary' not found.",
      ];
    }

    $created = [];
    $errors = [];

    foreach ($terms as $termData) {
      // Normalize to array format.
      if (is_string($termData)) {
        $termData = ['name' => $termData];
      }

      $result = $this->createTerm($vocabulary, $termData['name'], $termData);

      if ($result['success']) {
        $created[] = $result['data'];
      }
      else {
        $errors[] = [
          'name' => $termData['name'],
          'error' => $result['error'],
        ];
      }
    }

    return [
      'success' => empty($errors),
      'data' => [
        'vocabulary' => $vocabulary,
        'created_count' => count($created),
        'error_count' => count($errors),
        'created' => $created,
        'errors' => $errors,
      ],
    ];
  }

}
