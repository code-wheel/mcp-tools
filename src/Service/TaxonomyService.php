<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for taxonomy operations.
 */
class TaxonomyService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Get all vocabularies with term counts.
   *
   * @return array
   *   Vocabularies data.
   */
  public function getVocabularies(): array {
    $vocabStorage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    $vocabularies = $vocabStorage->loadMultiple();

    $result = [];
    foreach ($vocabularies as $vocab) {
      // Count terms in this vocabulary.
      $termCount = $termStorage->getQuery()
        ->accessCheck(TRUE)
        ->condition('vid', $vocab->id())
        ->count()
        ->execute();

      $result[] = [
        'id' => $vocab->id(),
        'label' => $vocab->label(),
        'description' => $vocab->getDescription(),
        'term_count' => (int) $termCount,
      ];
    }

    return [
      'total_vocabularies' => count($result),
      'vocabularies' => $result,
    ];
  }

  /**
   * Get terms from a vocabulary.
   *
   * @param string $vid
   *   Vocabulary ID.
   * @param int $limit
   *   Maximum terms to return.
   * @param bool $hierarchical
   *   If TRUE, return hierarchical structure.
   *
   * @return array
   *   Terms data.
   */
  public function getTerms(string $vid, int $limit = 100, bool $hierarchical = FALSE): array {
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');

    // Verify vocabulary exists.
    $vocab = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($vid);
    if (!$vocab) {
      return [
        'error' => "Vocabulary '$vid' not found.",
      ];
    }

    $query = $termStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', $vid)
      ->sort('weight')
      ->sort('name')
      ->range(0, $limit);

    $tids = $query->execute();
    $terms = $termStorage->loadMultiple($tids);

    $result = [];
    foreach ($terms as $term) {
      $parents = $termStorage->loadParents($term->id());
      $parentIds = array_keys($parents);

      $result[] = [
        'tid' => $term->id(),
        'name' => $term->getName(),
        'description' => $term->getDescription(),
        'weight' => $term->getWeight(),
        'parent_ids' => $parentIds,
        'depth' => count($parentIds),
      ];
    }

    if ($hierarchical) {
      $result = $this->buildHierarchy($result);
    }

    return [
      'vocabulary' => $vid,
      'vocabulary_label' => $vocab->label(),
      'total' => count($result),
      'terms' => $result,
    ];
  }

  /**
   * Build hierarchical term structure.
   *
   * @param array $terms
   *   Flat terms array.
   *
   * @return array
   *   Hierarchical structure.
   */
  protected function buildHierarchy(array $terms): array {
    $indexed = [];
    foreach ($terms as $term) {
      $indexed[$term['tid']] = $term;
      $indexed[$term['tid']]['children'] = [];
    }

    $tree = [];
    foreach ($indexed as $tid => &$term) {
      if (empty($term['parent_ids'])) {
        $tree[] = &$term;
      }
      else {
        $parentId = reset($term['parent_ids']);
        if (isset($indexed[$parentId])) {
          $indexed[$parentId]['children'][] = &$term;
        }
      }
    }

    return $tree;
  }

  /**
   * Search terms across all vocabularies.
   *
   * @param string $query
   *   Search query.
   * @param int $limit
   *   Maximum results.
   *
   * @return array
   *   Matching terms.
   */
  public function searchTerms(string $query, int $limit = 50): array {
    if (strlen($query) < 2) {
      return [
        'error' => 'Search query must be at least 2 characters.',
      ];
    }

    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');

    $tids = $termStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('name', '%' . $query . '%', 'LIKE')
      ->range(0, $limit)
      ->execute();

    $terms = $termStorage->loadMultiple($tids);

    $result = [];
    foreach ($terms as $term) {
      $result[] = [
        'tid' => $term->id(),
        'name' => $term->getName(),
        'vocabulary' => $term->bundle(),
      ];
    }

    return [
      'query' => $query,
      'total' => count($result),
      'terms' => $result,
    ];
  }

}
