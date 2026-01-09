<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for content accessibility analysis.
 */
class AccessibilityAnalyzer {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Check accessibility for a specific entity.
   *
   * @param string $entityType
   *   Entity type.
   * @param int $entityId
   *   Entity ID.
   *
   * @return array
   *   Accessibility check results.
   */
  public function checkAccessibility(string $entityType, int $entityId): array {
    try {
      $storage = $this->entityTypeManager->getStorage($entityType);
      $entity = $storage->load($entityId);

      if (!$entity) {
        return ['success' => FALSE, 'error' => "Entity {$entityType}/{$entityId} not found."];
      }

      $issues = [];
      $title = method_exists($entity, 'getTitle') ? $entity->getTitle() : ($entity->label() ?? '');

      // Collect all text content.
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
        // Check images for alt text.
        preg_match_all('/<img[^>]*>/i', $bodyContent, $images);
        foreach ($images[0] as $img) {
          if (!preg_match('/alt\s*=/', $img)) {
            $issues[] = [
              'type' => 'missing_alt',
              'severity' => 'error',
              'wcag' => '1.1.1',
              'message' => 'Image missing alt attribute.',
              'element' => substr($img, 0, 100),
            ];
          }
          elseif (preg_match('/alt\s*=\s*["\']["\']/', $img)) {
            // Empty alt - check if decorative.
            if (!preg_match('/role\s*=\s*["\']presentation["\']/', $img)) {
              $issues[] = [
                'type' => 'empty_alt',
                'severity' => 'warning',
                'wcag' => '1.1.1',
                'message' => 'Image has empty alt. If decorative, add role="presentation".',
                'element' => substr($img, 0, 100),
              ];
            }
          }
        }

        // Check heading hierarchy.
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $bodyContent, $headings, PREG_SET_ORDER);
        if (!empty($headings)) {
          $lastLevel = 0;
          foreach ($headings as $heading) {
            $level = (int) $heading[1];
            if ($lastLevel > 0 && $level > $lastLevel + 1) {
              $issues[] = [
                'type' => 'heading_skip',
                'severity' => 'warning',
                'wcag' => '1.3.1',
                'message' => "Heading level skipped from H{$lastLevel} to H{$level}.",
                'element' => substr($heading[0], 0, 50),
              ];
            }
            $lastLevel = $level;
          }
        }

        // Check for empty links.
        preg_match_all('/<a[^>]*>(.*?)<\/a>/is', $bodyContent, $links, PREG_SET_ORDER);
        foreach ($links as $link) {
          $linkText = strip_tags($link[1]);
          $linkText = trim($linkText);
          if (empty($linkText)) {
            // Check for aria-label.
            if (!preg_match('/aria-label\s*=/', $link[0])) {
              $issues[] = [
                'type' => 'empty_link',
                'severity' => 'error',
                'wcag' => '2.4.4',
                'message' => 'Link has no accessible text.',
                'element' => substr($link[0], 0, 100),
              ];
            }
          }
          elseif (in_array(strtolower($linkText), ['click here', 'read more', 'here', 'more', 'link'])) {
            $issues[] = [
              'type' => 'generic_link_text',
              'severity' => 'warning',
              'wcag' => '2.4.4',
              'message' => "Link text '{$linkText}' is not descriptive.",
              'element' => substr($link[0], 0, 100),
            ];
          }
        }

        // Check for tables without headers.
        preg_match_all('/<table[^>]*>.*?<\/table>/is', $bodyContent, $tables);
        foreach ($tables[0] as $table) {
          if (!preg_match('/<th[^>]*>/i', $table)) {
            $issues[] = [
              'type' => 'table_no_headers',
              'severity' => 'error',
              'wcag' => '1.3.1',
              'message' => 'Table has no header cells (th).',
            ];
          }
        }

        // Check for color contrast indicators (text mentioning colors).
        if (preg_match('/\b(red|green|blue|click the colored)\b/i', strip_tags($bodyContent))) {
          $issues[] = [
            'type' => 'color_reference',
            'severity' => 'info',
            'wcag' => '1.4.1',
            'message' => 'Content may reference color. Ensure color is not the only means of conveying information.',
          ];
        }
      }

      $errorCount = count(array_filter($issues, fn($i) => $i['severity'] === 'error'));
      $warningCount = count(array_filter($issues, fn($i) => $i['severity'] === 'warning'));

      $suggestions = [];
      if ($errorCount > 0) {
        $suggestions[] = 'Fix error-level accessibility issues first.';
      }
      if (count(array_filter($issues, fn($i) => $i['type'] === 'missing_alt')) > 0) {
        $suggestions[] = 'Add descriptive alt text to all informative images.';
      }
      if (count(array_filter($issues, fn($i) => $i['type'] === 'generic_link_text')) > 0) {
        $suggestions[] = 'Use descriptive link text that makes sense out of context.';
      }
      $suggestions[] = 'Consider running a full accessibility audit with tools like WAVE or axe.';

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'entity_id' => $entityId,
          'title' => $title,
          'issues' => $issues,
          'error_count' => $errorCount,
          'warning_count' => $warningCount,
          'info_count' => count($issues) - $errorCount - $warningCount,
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to check accessibility: ' . $e->getMessage()];
    }
  }

}
