<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_scheduler\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for scheduling content publish/unpublish operations.
 *
 * Integrates with the Scheduler contrib module which adds publish_on
 * and unpublish_on fields to nodes.
 */
class SchedulerService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
    protected TimeInterface $time,
  ) {}

  /**
   * Get scheduled content.
   *
   * @param string $type
   *   Filter by schedule type: 'publish', 'unpublish', or 'all'.
   * @param int $limit
   *   Maximum number of items to return.
   *
   * @return array
   *   Result array with scheduled content.
   */
  public function getScheduledContent(string $type = 'all', int $limit = 50): array {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $results = [];

    try {
      if ($type === 'publish' || $type === 'all') {
        $query = $nodeStorage->getQuery()
          ->accessCheck(TRUE)
          ->condition('publish_on', 0, '>')
          ->sort('publish_on', 'ASC')
          ->range(0, $limit);

        $nids = $query->execute();
        if (!empty($nids)) {
          $nodes = $nodeStorage->loadMultiple($nids);
          foreach ($nodes as $node) {
            $results[] = [
              'nid' => (int) $node->id(),
              'title' => $node->getTitle(),
              'type' => $node->bundle(),
              'schedule_type' => 'publish',
              'scheduled_date' => date('Y-m-d H:i:s', (int) $node->get('publish_on')->value),
              'timestamp' => (int) $node->get('publish_on')->value,
              'status' => $node->isPublished() ? 'published' : 'unpublished',
            ];
          }
        }
      }

      if ($type === 'unpublish' || $type === 'all') {
        $query = $nodeStorage->getQuery()
          ->accessCheck(TRUE)
          ->condition('unpublish_on', 0, '>')
          ->sort('unpublish_on', 'ASC')
          ->range(0, $limit);

        $nids = $query->execute();
        if (!empty($nids)) {
          $nodes = $nodeStorage->loadMultiple($nids);
          foreach ($nodes as $node) {
            $results[] = [
              'nid' => (int) $node->id(),
              'title' => $node->getTitle(),
              'type' => $node->bundle(),
              'schedule_type' => 'unpublish',
              'scheduled_date' => date('Y-m-d H:i:s', (int) $node->get('unpublish_on')->value),
              'timestamp' => (int) $node->get('unpublish_on')->value,
              'status' => $node->isPublished() ? 'published' : 'unpublished',
            ];
          }
        }
      }

      // Sort combined results by timestamp.
      usort($results, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

      // Apply limit after combining.
      if (count($results) > $limit) {
        $results = array_slice($results, 0, $limit);
      }

      return [
        'success' => TRUE,
        'data' => [
          'items' => $results,
          'count' => count($results),
          'filter' => $type,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to retrieve scheduled content: ' . $e->getMessage()];
    }
  }

  /**
   * Schedule content for publication.
   *
   * @param string $entityType
   *   The entity type (currently only 'node' is supported).
   * @param int $entityId
   *   The entity ID.
   * @param int $timestamp
   *   Unix timestamp for scheduled publication.
   *
   * @return array
   *   Result array.
   */
  public function schedulePublish(string $entityType, int $entityId, int $timestamp): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    if ($entityType !== 'node') {
      return ['success' => FALSE, 'error' => "Entity type '$entityType' is not supported. Only 'node' is currently supported."];
    }

    $node = $this->entityTypeManager->getStorage('node')->load($entityId);
    if (!$node) {
      return ['success' => FALSE, 'error' => "Node with ID $entityId not found."];
    }

    if (!$node->hasField('publish_on')) {
      return ['success' => FALSE, 'error' => "Content type '{$node->bundle()}' is not configured for scheduling. Enable scheduling for this content type in the Scheduler settings."];
    }

    $currentTime = $this->time->getRequestTime();
    if ($timestamp <= $currentTime) {
      return ['success' => FALSE, 'error' => 'Scheduled time must be in the future.'];
    }

    try {
      $node->set('publish_on', $timestamp);
      $node->save();

      $this->auditLogger->logSuccess('schedule_publish', 'node', (string) $entityId, [
        'title' => $node->getTitle(),
        'scheduled_date' => date('Y-m-d H:i:s', $timestamp),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'nid' => $entityId,
          'title' => $node->getTitle(),
          'type' => $node->bundle(),
          'scheduled_publish' => date('Y-m-d H:i:s', $timestamp),
          'timestamp' => $timestamp,
          'message' => "Content '{$node->getTitle()}' scheduled for publication on " . date('Y-m-d H:i:s', $timestamp) . ".",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('schedule_publish', 'node', (string) $entityId, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to schedule publication: ' . $e->getMessage()];
    }
  }

  /**
   * Schedule content for unpublication.
   *
   * @param string $entityType
   *   The entity type (currently only 'node' is supported).
   * @param int $entityId
   *   The entity ID.
   * @param int $timestamp
   *   Unix timestamp for scheduled unpublication.
   *
   * @return array
   *   Result array.
   */
  public function scheduleUnpublish(string $entityType, int $entityId, int $timestamp): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    if ($entityType !== 'node') {
      return ['success' => FALSE, 'error' => "Entity type '$entityType' is not supported. Only 'node' is currently supported."];
    }

    $node = $this->entityTypeManager->getStorage('node')->load($entityId);
    if (!$node) {
      return ['success' => FALSE, 'error' => "Node with ID $entityId not found."];
    }

    if (!$node->hasField('unpublish_on')) {
      return ['success' => FALSE, 'error' => "Content type '{$node->bundle()}' is not configured for scheduling. Enable scheduling for this content type in the Scheduler settings."];
    }

    $currentTime = $this->time->getRequestTime();
    if ($timestamp <= $currentTime) {
      return ['success' => FALSE, 'error' => 'Scheduled time must be in the future.'];
    }

    try {
      $node->set('unpublish_on', $timestamp);
      $node->save();

      $this->auditLogger->logSuccess('schedule_unpublish', 'node', (string) $entityId, [
        'title' => $node->getTitle(),
        'scheduled_date' => date('Y-m-d H:i:s', $timestamp),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'nid' => $entityId,
          'title' => $node->getTitle(),
          'type' => $node->bundle(),
          'scheduled_unpublish' => date('Y-m-d H:i:s', $timestamp),
          'timestamp' => $timestamp,
          'message' => "Content '{$node->getTitle()}' scheduled for unpublication on " . date('Y-m-d H:i:s', $timestamp) . ".",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('schedule_unpublish', 'node', (string) $entityId, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to schedule unpublication: ' . $e->getMessage()];
    }
  }

  /**
   * Cancel scheduled publish/unpublish.
   *
   * @param string $entityType
   *   The entity type (currently only 'node' is supported).
   * @param int $entityId
   *   The entity ID.
   * @param string $type
   *   Which schedule to cancel: 'publish', 'unpublish', or 'all'.
   *
   * @return array
   *   Result array.
   */
  public function cancelSchedule(string $entityType, int $entityId, string $type = 'all'): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    if ($entityType !== 'node') {
      return ['success' => FALSE, 'error' => "Entity type '$entityType' is not supported. Only 'node' is currently supported."];
    }

    $node = $this->entityTypeManager->getStorage('node')->load($entityId);
    if (!$node) {
      return ['success' => FALSE, 'error' => "Node with ID $entityId not found."];
    }

    try {
      $cancelled = [];

      if (($type === 'publish' || $type === 'all') && $node->hasField('publish_on')) {
        $previousValue = $node->get('publish_on')->value;
        if ($previousValue) {
          $node->set('publish_on', NULL);
          $cancelled[] = 'publish';
        }
      }

      if (($type === 'unpublish' || $type === 'all') && $node->hasField('unpublish_on')) {
        $previousValue = $node->get('unpublish_on')->value;
        if ($previousValue) {
          $node->set('unpublish_on', NULL);
          $cancelled[] = 'unpublish';
        }
      }

      if (empty($cancelled)) {
        return [
          'success' => TRUE,
          'data' => [
            'nid' => $entityId,
            'title' => $node->getTitle(),
            'cancelled' => [],
            'message' => 'No scheduled actions found to cancel.',
          ],
        ];
      }

      $node->save();

      $this->auditLogger->logSuccess('cancel_schedule', 'node', (string) $entityId, [
        'title' => $node->getTitle(),
        'cancelled' => $cancelled,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'nid' => $entityId,
          'title' => $node->getTitle(),
          'type' => $node->bundle(),
          'cancelled' => $cancelled,
          'message' => "Cancelled " . implode(' and ', $cancelled) . " schedule for '{$node->getTitle()}'.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('cancel_schedule', 'node', (string) $entityId, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to cancel schedule: ' . $e->getMessage()];
    }
  }

  /**
   * Get schedule info for specific content.
   *
   * @param string $entityType
   *   The entity type (currently only 'node' is supported).
   * @param int $entityId
   *   The entity ID.
   *
   * @return array
   *   Result array with schedule information.
   */
  public function getSchedule(string $entityType, int $entityId): array {
    if ($entityType !== 'node') {
      return ['success' => FALSE, 'error' => "Entity type '$entityType' is not supported. Only 'node' is currently supported."];
    }

    $node = $this->entityTypeManager->getStorage('node')->load($entityId);
    if (!$node) {
      return ['success' => FALSE, 'error' => "Node with ID $entityId not found."];
    }

    try {
      $publishOn = NULL;
      $unpublishOn = NULL;

      if ($node->hasField('publish_on') && $node->get('publish_on')->value) {
        $timestamp = (int) $node->get('publish_on')->value;
        $publishOn = [
          'date' => date('Y-m-d H:i:s', $timestamp),
          'timestamp' => $timestamp,
        ];
      }

      if ($node->hasField('unpublish_on') && $node->get('unpublish_on')->value) {
        $timestamp = (int) $node->get('unpublish_on')->value;
        $unpublishOn = [
          'date' => date('Y-m-d H:i:s', $timestamp),
          'timestamp' => $timestamp,
        ];
      }

      $schedulingEnabled = $node->hasField('publish_on') || $node->hasField('unpublish_on');

      return [
        'success' => TRUE,
        'data' => [
          'nid' => $entityId,
          'title' => $node->getTitle(),
          'type' => $node->bundle(),
          'status' => $node->isPublished() ? 'published' : 'unpublished',
          'scheduling_enabled' => $schedulingEnabled,
          'publish_on' => $publishOn,
          'unpublish_on' => $unpublishOn,
          'has_schedule' => $publishOn !== NULL || $unpublishOn !== NULL,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to get schedule: ' . $e->getMessage()];
    }
  }

}
