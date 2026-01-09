<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for detecting duplicate content.
 */
class DuplicateDetector {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Find duplicate content based on field similarity.
   *
   * @param string $contentType
   *   Content type machine name.
   * @param string $field
   *   Field to compare (default: 'title').
   * @param float $threshold
   *   Similarity threshold 0-1 (default: 0.8).
   *
   * @return array
   *   Results with potential duplicates.
   */
  public function findDuplicateContent(string $contentType, string $field = 'title', float $threshold = 0.8): array {
    try {
      $nodeStorage = $this->entityTypeManager->getStorage('node');

      // Verify content type exists.
      $nodeTypeStorage = $this->entityTypeManager->getStorage('node_type');
      if (!$nodeTypeStorage->load($contentType)) {
        return ['success' => FALSE, 'error' => "Content type '{$contentType}' not found."];
      }

      // Load all nodes of this type.
      $query = $nodeStorage->getQuery()
        ->condition('type', $contentType)
        ->accessCheck(FALSE)
        ->range(0, 500);
      $nids = $query->execute();
      $nodes = $nodeStorage->loadMultiple($nids);

      // Extract values for comparison.
      $items = [];
      foreach ($nodes as $node) {
        $value = '';
        if ($field === 'title') {
          $value = $node->getTitle();
        }
        elseif ($node->hasField($field)) {
          $fieldValue = $node->get($field)->getValue();
          if (!empty($fieldValue[0]['value'])) {
            $value = strip_tags($fieldValue[0]['value']);
          }
        }
        elseif ($node->hasField('field_' . $field)) {
          $fieldValue = $node->get('field_' . $field)->getValue();
          if (!empty($fieldValue[0]['value'])) {
            $value = strip_tags($fieldValue[0]['value']);
          }
        }

        if (!empty($value)) {
          $items[] = [
            'nid' => $node->id(),
            'title' => $node->getTitle(),
            'value' => $value,
            'status' => $node->isPublished() ? 'published' : 'unpublished',
            'created' => $node->getCreatedTime(),
          ];
        }
      }

      // Find duplicates using similarity comparison.
      $duplicates = [];
      $compared = [];

      for ($i = 0; $i < count($items); $i++) {
        for ($j = $i + 1; $j < count($items); $j++) {
          $key = $items[$i]['nid'] . '-' . $items[$j]['nid'];
          if (isset($compared[$key])) {
            continue;
          }
          $compared[$key] = TRUE;

          // Calculate similarity.
          $similarity = $this->calculateSimilarity($items[$i]['value'], $items[$j]['value']);

          if ($similarity >= $threshold) {
            $duplicates[] = [
              'item1' => [
                'nid' => $items[$i]['nid'],
                'title' => $items[$i]['title'],
                'status' => $items[$i]['status'],
                'created' => date('Y-m-d', $items[$i]['created']),
              ],
              'item2' => [
                'nid' => $items[$j]['nid'],
                'title' => $items[$j]['title'],
                'status' => $items[$j]['status'],
                'created' => date('Y-m-d', $items[$j]['created']),
              ],
              'similarity' => round($similarity * 100, 1) . '%',
              'field_compared' => $field,
            ];
          }
        }
      }

      // Sort by similarity descending.
      usort($duplicates, fn($a, $b) => floatval($b['similarity']) <=> floatval($a['similarity']));

      $suggestions = [];
      if (!empty($duplicates)) {
        $suggestions[] = 'Review potential duplicates and merge or delete as appropriate.';
        $suggestions[] = 'Keep the older/more complete version and redirect the duplicate.';
        $suggestions[] = 'Consider using the Entity Clone module to track intentional copies.';
      }
      else {
        $suggestions[] = 'No duplicates found at the current threshold. Try lowering the threshold to find more similar content.';
      }

      return [
        'success' => TRUE,
        'data' => [
          'content_type' => $contentType,
          'field_compared' => $field,
          'threshold' => $threshold,
          'items_analyzed' => count($items),
          'duplicates' => $duplicates,
          'duplicate_count' => count($duplicates),
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to find duplicates: ' . $e->getMessage()];
    }
  }

  /**
   * Calculate similarity between two strings.
   *
   * @param string $str1
   *   First string.
   * @param string $str2
   *   Second string.
   *
   * @return float
   *   Similarity score between 0 and 1.
   */
  public function calculateSimilarity(string $str1, string $str2): float {
    // Normalize strings.
    $str1 = strtolower(trim($str1));
    $str2 = strtolower(trim($str2));

    if ($str1 === $str2) {
      return 1.0;
    }

    if (empty($str1) || empty($str2)) {
      return 0.0;
    }

    // Use similar_text for percentage.
    $similarity = 0;
    similar_text($str1, $str2, $similarity);

    return $similarity / 100;
  }

}
