<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for auditing content health and status.
 */
class ContentAuditor {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
  ) {}

  /**
   * Audit content for stale, orphaned, or draft content.
   *
   * @param array $options
   *   Options including:
   *   - stale_days: Days since last update to consider stale (default: 365).
   *   - include_drafts: Include draft content (default: true).
   *   - content_types: Array of content types to audit (default: all).
   *
   * @return array
   *   Audit results.
   */
  public function contentAudit(array $options = []): array {
    $staleDays = $options['stale_days'] ?? 365;
    $includeDrafts = $options['include_drafts'] ?? TRUE;
    $contentTypes = $options['content_types'] ?? [];

    try {
      $nodeStorage = $this->entityTypeManager->getStorage('node');
      $results = [
        'stale_content' => [],
        'orphaned_content' => [],
        'drafts' => [],
      ];

      // Find stale content (not updated in X days).
      $staleTimestamp = strtotime("-{$staleDays} days");
      $query = $nodeStorage->getQuery()
        ->condition('changed', $staleTimestamp, '<')
        ->condition('status', 1)
        ->accessCheck(FALSE)
        ->range(0, 50);

      if (!empty($contentTypes)) {
        $query->condition('type', $contentTypes, 'IN');
      }

      $staleNids = $query->execute();
      $staleNodes = $nodeStorage->loadMultiple($staleNids);

      foreach ($staleNodes as $node) {
        $results['stale_content'][] = [
          'nid' => $node->id(),
          'title' => $node->getTitle(),
          'type' => $node->bundle(),
          'last_updated' => date('Y-m-d', $node->getChangedTime()),
          'days_since_update' => floor((time() - $node->getChangedTime()) / 86400),
        ];
      }

      // Find orphaned content (unpublished with no recent views).
      $query = $nodeStorage->getQuery()
        ->condition('status', 0)
        ->accessCheck(FALSE)
        ->range(0, 50);

      if (!empty($contentTypes)) {
        $query->condition('type', $contentTypes, 'IN');
      }

      $orphanedNids = $query->execute();
      $orphanedNodes = $nodeStorage->loadMultiple($orphanedNids);

      foreach ($orphanedNodes as $node) {
        $results['orphaned_content'][] = [
          'nid' => $node->id(),
          'title' => $node->getTitle(),
          'type' => $node->bundle(),
          'created' => date('Y-m-d', $node->getCreatedTime()),
          'last_updated' => date('Y-m-d', $node->getChangedTime()),
        ];
      }

      // Find drafts if requested.
      if ($includeDrafts) {
        // Check for content moderation drafts if module exists.
        if ($this->entityTypeManager->hasDefinition('content_moderation_state')) {
          $query = $this->database->select('content_moderation_state_field_data', 'cms')
            ->fields('cms', ['content_entity_id', 'moderation_state'])
            ->condition('cms.content_entity_type_id', 'node')
            ->condition('cms.moderation_state', 'draft')
            ->range(0, 50);
          $draftResults = $query->execute()->fetchAll();

          $draftNids = array_column($draftResults, 'content_entity_id');
          if (!empty($draftNids)) {
            $draftNodes = $nodeStorage->loadMultiple($draftNids);
            foreach ($draftNodes as $node) {
              $results['drafts'][] = [
                'nid' => $node->id(),
                'title' => $node->getTitle(),
                'type' => $node->bundle(),
                'author' => $node->getOwner()->getDisplayName(),
                'created' => date('Y-m-d', $node->getCreatedTime()),
              ];
            }
          }
        }
        else {
          // Fallback: unpublished content as "drafts".
          $results['drafts'] = $results['orphaned_content'];
        }
      }

      $suggestions = [];
      if (!empty($results['stale_content'])) {
        $suggestions[] = 'Consider reviewing and updating stale content to keep it relevant.';
        $suggestions[] = 'Archive or unpublish content that is no longer needed.';
      }
      if (!empty($results['orphaned_content'])) {
        $suggestions[] = 'Review orphaned content - consider deleting or republishing.';
      }
      if (!empty($results['drafts'])) {
        $suggestions[] = 'Review draft content and either publish or discard.';
      }

      return [
        'success' => TRUE,
        'data' => [
          'stale_content' => $results['stale_content'],
          'stale_count' => count($results['stale_content']),
          'orphaned_content' => $results['orphaned_content'],
          'orphaned_count' => count($results['orphaned_content']),
          'drafts' => $results['drafts'],
          'draft_count' => count($results['drafts']),
          'audit_options' => [
            'stale_days' => $staleDays,
            'content_types' => $contentTypes ?: 'all',
          ],
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to perform content audit: ' . $e->getMessage()];
    }
  }

}
