<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for taxonomy operations.
 */
class TaxonomyService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
  ) {}

  /**
   * Get all vocabularies with term counts.
   *
   * @return array
   *   Vocabularies data.
   */
  public function getVocabularies(): array {
    $vocabStorage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    $vocabularies = $vocabStorage->loadMultiple();

    // Get all term counts in a single aggregated query to avoid N+1.
    $termCounts = $this->getTermCountsByVocabulary();

    $result = [];
    foreach ($vocabularies as $vocab) {
      $vid = $vocab->id();
      $result[] = [
        'id' => $vid,
        'label' => $vocab->label(),
        'description' => $vocab->getDescription(),
        'term_count' => $termCounts[$vid] ?? 0,
      ];
    }

    return [
      'total_vocabularies' => count($result),
      'vocabularies' => $result,
    ];
  }

  /**
   * Get term counts for all vocabularies in a single query.
   *
   * @return array<string, int>
   *   Term counts keyed by vocabulary ID.
   */
  protected function getTermCountsByVocabulary(): array {
    $connection = $this->database;
    $query = $connection->select('taxonomy_term_field_data', 't')
      ->fields('t', ['vid'])
      ->groupBy('t.vid');
    $query->addExpression('COUNT(DISTINCT t.tid)', 'term_count');

    $results = $query->execute()->fetchAllKeyed();
    return array_map('intval', $results);
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
        'error' => "Vocabulary '$vid' not found. Use mcp_structure_list_vocabularies to see available vocabularies.",
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

    // Batch load all parent relationships in a single query to avoid N+1.
    $parentMap = $this->batchLoadParents(array_keys($tids));

    $result = [];
    foreach ($terms as $term) {
      $tid = $term->id();
      $parentIds = $parentMap[$tid] ?? [];

      $result[] = [
        'tid' => $tid,
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

  /**
   * Batch load parent relationships for multiple terms.
   *
   * @param array $tids
   *   Array of term IDs.
   *
   * @return array<int, array<int>>
   *   Map of term ID to array of parent term IDs.
   */
  protected function batchLoadParents(array $tids): array {
    if (empty($tids)) {
      return [];
    }

    $connection = $this->database;
    $query = $connection->select('taxonomy_term__parent', 'p')
      ->fields('p', ['entity_id', 'parent_target_id'])
      ->condition('p.entity_id', $tids, 'IN')
      ->condition('p.parent_target_id', 0, '>')
      ->orderBy('p.entity_id');

    $results = $query->execute()->fetchAll();

    $parentMap = [];
    foreach ($tids as $tid) {
      $parentMap[$tid] = [];
    }
    foreach ($results as $row) {
      $parentMap[$row->entity_id][] = (int) $row->parent_target_id;
    }

    return $parentMap;
  }

}
