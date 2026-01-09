<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Service for SEO analysis of content.
 */
class SeoAnalyzer {

  /**
   * Max bytes allowed for serialized metatag field data.
   *
   * Prevents memory exhaustion on crafted field payloads.
   */
  private const MAX_SERIALIZED_METATAG_BYTES = 65536;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Analyze SEO for a specific entity.
   *
   * @param string $entityType
   *   Entity type (e.g., 'node', 'taxonomy_term').
   * @param int $entityId
   *   Entity ID.
   *
   * @return array
   *   SEO analysis results.
   */
  public function analyzeSeo(string $entityType, int $entityId): array {
    try {
      $storage = $this->entityTypeManager->getStorage($entityType);
      $entity = $storage->load($entityId);

      if (!$entity) {
        return ['success' => FALSE, 'error' => "Entity {$entityType}/{$entityId} not found."];
      }

      $issues = [];
      $score = 100;
      $title = method_exists($entity, 'getTitle') ? $entity->getTitle() : ($entity->label() ?? '');

      // Check title length.
      $titleLength = strlen($title);
      if ($titleLength < 30) {
        $issues[] = [
          'type' => 'title_short',
          'severity' => 'warning',
          'message' => "Title is too short ({$titleLength} chars). Recommended: 30-60 characters.",
        ];
        $score -= 10;
      }
      elseif ($titleLength > 60) {
        $issues[] = [
          'type' => 'title_long',
          'severity' => 'warning',
          'message' => "Title is too long ({$titleLength} chars). May be truncated in search results.",
        ];
        $score -= 5;
      }

      // Check for meta tags if metatag module is enabled.
      $hasMetaDescription = FALSE;
      if ($this->moduleHandler->moduleExists('metatag') && $entity->hasField('field_metatag')) {
        $metatag = $entity->get('field_metatag')->value;
        if (!empty($metatag)) {
          if (is_string($metatag) && strlen($metatag) <= self::MAX_SERIALIZED_METATAG_BYTES) {
            $metatagData = @unserialize($metatag, ['allowed_classes' => FALSE]);
            if (is_array($metatagData) && !empty($metatagData['description'])) {
              $hasMetaDescription = TRUE;
              $descLength = strlen((string) $metatagData['description']);
              if ($descLength < 120 || $descLength > 160) {
                $issues[] = [
                  'type' => 'meta_description_length',
                  'severity' => 'info',
                  'message' => "Meta description length ({$descLength}) not optimal. Recommended: 120-160 characters.",
                ];
                $score -= 5;
              }
            }
          }
        }
      }

      if (!$hasMetaDescription) {
        $issues[] = [
          'type' => 'missing_meta_description',
          'severity' => 'warning',
          'message' => 'No meta description found. Add one for better search visibility.',
        ];
        $score -= 15;
      }

      // Analyze body content for headings and images.
      $bodyContent = '';
      foreach ($entity->getFields() as $field) {
        $fieldType = $field->getFieldDefinition()->getType();
        if (in_array($fieldType, ['text', 'text_long', 'text_with_summary'])) {
          foreach ($field as $item) {
            $bodyContent .= $item->value ?? '';
          }
        }
      }

      if (!empty($bodyContent)) {
        // Check heading structure.
        preg_match_all('/<h([1-6])[^>]*>/i', $bodyContent, $headings);
        if (empty($headings[1])) {
          $issues[] = [
            'type' => 'no_headings',
            'severity' => 'warning',
            'message' => 'No headings found in content. Use H2-H6 to structure content.',
          ];
          $score -= 10;
        }
        else {
          // Check heading hierarchy.
          $headingLevels = array_map('intval', $headings[1]);
          if (min($headingLevels) === 1) {
            $issues[] = [
              'type' => 'h1_in_content',
              'severity' => 'warning',
              'message' => 'H1 found in body content. H1 should be reserved for page title.',
            ];
            $score -= 5;
          }
        }

        // Check images for alt text.
        preg_match_all('/<img[^>]*>/i', $bodyContent, $images);
        if (!empty($images[0])) {
          $missingAlt = 0;
          foreach ($images[0] as $img) {
            if (!preg_match('/alt\s*=\s*["\'][^"\']+["\']/', $img)) {
              $missingAlt++;
            }
          }
          if ($missingAlt > 0) {
            $issues[] = [
              'type' => 'missing_alt_text',
              'severity' => 'error',
              'message' => "{$missingAlt} image(s) missing alt text. Required for SEO and accessibility.",
            ];
            $score -= ($missingAlt * 10);
          }
        }

        // Check content length.
        $wordCount = str_word_count(strip_tags($bodyContent));
        if ($wordCount < 300) {
          $issues[] = [
            'type' => 'thin_content',
            'severity' => 'info',
            'message' => "Content is thin ({$wordCount} words). Consider adding more content (300+ words recommended).",
          ];
          $score -= 5;
        }
      }
      else {
        $issues[] = [
          'type' => 'no_content',
          'severity' => 'error',
          'message' => 'No body content found.',
        ];
        $score -= 20;
      }

      $score = max(0, $score);

      $suggestions = [];
      if ($score < 70) {
        $suggestions[] = 'Focus on addressing error-level issues first.';
      }
      if (!$hasMetaDescription) {
        $suggestions[] = 'Install and configure the Metatag module for better SEO control.';
      }

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'entity_id' => $entityId,
          'title' => $title,
          'seo_score' => $score,
          'score_rating' => $score >= 80 ? 'good' : ($score >= 60 ? 'needs_improvement' : 'poor'),
          'issues' => $issues,
          'issue_count' => count($issues),
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to analyze SEO: ' . $e->getMessage()];
    }
  }

}
