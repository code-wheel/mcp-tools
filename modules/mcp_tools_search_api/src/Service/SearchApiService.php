<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_search_api\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ServerInterface;

/**
 * Service for Search API operations.
 */
class SearchApiService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * List all search indexes with status.
   *
   * @return array
   *   List of indexes with their status.
   */
  public function listIndexes(): array {
    $storage = $this->entityTypeManager->getStorage('search_api_index');
    $indexes = $storage->loadMultiple();

    $result = [];
    foreach ($indexes as $index) {
      /** @var \Drupal\search_api\IndexInterface $index */
      $result[] = [
        'id' => $index->id(),
        'label' => $index->label(),
        'status' => $index->status() ? 'enabled' : 'disabled',
        'server' => $index->getServerId(),
        'read_only' => $index->isReadOnly(),
      ];
    }

    return [
      'success' => TRUE,
      'total' => count($result),
      'indexes' => $result,
    ];
  }

  /**
   * Get index details.
   *
   * @param string $id
   *   The index ID.
   *
   * @return array
   *   Index details including fields, datasources, and status.
   */
  public function getIndex(string $id): array {
    $index = $this->loadIndex($id);
    if (!$index) {
      return [
        'success' => FALSE,
        'error' => "Index '$id' not found.",
        'code' => 'NOT_FOUND',
      ];
    }

    $fields = [];
    foreach ($index->getFields() as $field) {
      $fields[] = [
        'id' => $field->getFieldIdentifier(),
        'label' => $field->getLabel(),
        'type' => $field->getType(),
        'datasource' => $field->getDatasourceId(),
        'property_path' => $field->getPropertyPath(),
      ];
    }

    $datasources = [];
    foreach ($index->getDatasources() as $datasource) {
      $datasources[] = [
        'id' => $datasource->getPluginId(),
        'label' => $datasource->label(),
      ];
    }

    $tracker = $index->getTrackerInstance();
    $indexedCount = $tracker->getIndexedItemsCount();
    $totalCount = $tracker->getTotalItemsCount();

    return [
      'success' => TRUE,
      'index' => [
        'id' => $index->id(),
        'label' => $index->label(),
        'description' => $index->getDescription(),
        'status' => $index->status() ? 'enabled' : 'disabled',
        'server' => $index->getServerId(),
        'read_only' => $index->isReadOnly(),
        'datasources' => $datasources,
        'fields' => $fields,
        'field_count' => count($fields),
        'indexed' => $indexedCount,
        'total' => $totalCount,
        'remaining' => $totalCount - $indexedCount,
      ],
    ];
  }

  /**
   * Get indexing status for an index.
   *
   * @param string $id
   *   The index ID.
   *
   * @return array
   *   Indexing status with counts.
   */
  public function getIndexStatus(string $id): array {
    $index = $this->loadIndex($id);
    if (!$index) {
      return [
        'success' => FALSE,
        'error' => "Index '$id' not found.",
        'code' => 'NOT_FOUND',
      ];
    }

    $tracker = $index->getTrackerInstance();
    $indexedCount = $tracker->getIndexedItemsCount();
    $totalCount = $tracker->getTotalItemsCount();
    $remaining = $totalCount - $indexedCount;
    $percentage = $totalCount > 0 ? round(($indexedCount / $totalCount) * 100, 2) : 100;

    return [
      'success' => TRUE,
      'index_id' => $id,
      'label' => $index->label(),
      'status' => [
        'total' => $totalCount,
        'indexed' => $indexedCount,
        'remaining' => $remaining,
        'percentage' => $percentage,
        'is_complete' => $remaining === 0,
      ],
    ];
  }

  /**
   * Mark all items for reindexing.
   *
   * @param string $id
   *   The index ID.
   *
   * @return array
   *   Result of the operation.
   */
  public function reindexIndex(string $id): array {
    $index = $this->loadIndex($id);
    if (!$index) {
      return [
        'success' => FALSE,
        'error' => "Index '$id' not found.",
        'code' => 'NOT_FOUND',
      ];
    }

    if (!$index->status()) {
      return [
        'success' => FALSE,
        'error' => "Index '$id' is disabled.",
        'code' => 'INDEX_DISABLED',
      ];
    }

    if ($index->isReadOnly()) {
      return [
        'success' => FALSE,
        'error' => "Index '$id' is read-only.",
        'code' => 'INDEX_READ_ONLY',
      ];
    }

    try {
      $index->reindex();
      $tracker = $index->getTrackerInstance();

      return [
        'success' => TRUE,
        'message' => "All items marked for reindexing on index '$id'.",
        'index_id' => $id,
        'total_items' => $tracker->getTotalItemsCount(),
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => "Failed to reindex: " . $e->getMessage(),
        'code' => 'INTERNAL_ERROR',
      ];
    }
  }

  /**
   * Index a batch of items.
   *
   * @param string $id
   *   The index ID.
   * @param int $limit
   *   Maximum number of items to index.
   *
   * @return array
   *   Result of the operation.
   */
  public function indexItems(string $id, int $limit = 100): array {
    $index = $this->loadIndex($id);
    if (!$index) {
      return [
        'success' => FALSE,
        'error' => "Index '$id' not found.",
        'code' => 'NOT_FOUND',
      ];
    }

    if (!$index->status()) {
      return [
        'success' => FALSE,
        'error' => "Index '$id' is disabled.",
        'code' => 'INDEX_DISABLED',
      ];
    }

    if ($index->isReadOnly()) {
      return [
        'success' => FALSE,
        'error' => "Index '$id' is read-only.",
        'code' => 'INDEX_READ_ONLY',
      ];
    }

    try {
      $indexed = $index->indexItems($limit);
      $tracker = $index->getTrackerInstance();
      $remaining = $tracker->getTotalItemsCount() - $tracker->getIndexedItemsCount();

      return [
        'success' => TRUE,
        'message' => "Indexed $indexed items on index '$id'.",
        'index_id' => $id,
        'items_indexed' => $indexed,
        'remaining' => $remaining,
        'is_complete' => $remaining === 0,
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => "Failed to index items: " . $e->getMessage(),
        'code' => 'INTERNAL_ERROR',
      ];
    }
  }

  /**
   * Clear all indexed data from an index.
   *
   * @param string $id
   *   The index ID.
   *
   * @return array
   *   Result of the operation.
   */
  public function clearIndex(string $id): array {
    $index = $this->loadIndex($id);
    if (!$index) {
      return [
        'success' => FALSE,
        'error' => "Index '$id' not found.",
        'code' => 'NOT_FOUND',
      ];
    }

    if (!$index->status()) {
      return [
        'success' => FALSE,
        'error' => "Index '$id' is disabled.",
        'code' => 'INDEX_DISABLED',
      ];
    }

    if ($index->isReadOnly()) {
      return [
        'success' => FALSE,
        'error' => "Index '$id' is read-only.",
        'code' => 'INDEX_READ_ONLY',
      ];
    }

    try {
      $index->clear();
      $tracker = $index->getTrackerInstance();

      return [
        'success' => TRUE,
        'message' => "Index '$id' has been cleared.",
        'index_id' => $id,
        'items_to_reindex' => $tracker->getTotalItemsCount(),
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => "Failed to clear index: " . $e->getMessage(),
        'code' => 'INTERNAL_ERROR',
      ];
    }
  }

  /**
   * List all search servers.
   *
   * @return array
   *   List of servers.
   */
  public function listServers(): array {
    $storage = $this->entityTypeManager->getStorage('search_api_server');
    $servers = $storage->loadMultiple();

    $result = [];
    foreach ($servers as $server) {
      /** @var \Drupal\search_api\ServerInterface $server */
      $result[] = [
        'id' => $server->id(),
        'label' => $server->label(),
        'status' => $server->status() ? 'enabled' : 'disabled',
        'backend' => $server->getBackendId(),
      ];
    }

    return [
      'success' => TRUE,
      'total' => count($result),
      'servers' => $result,
    ];
  }

  /**
   * Get server details.
   *
   * @param string $id
   *   The server ID.
   *
   * @return array
   *   Server details.
   */
  public function getServer(string $id): array {
    $storage = $this->entityTypeManager->getStorage('search_api_server');
    $server = $storage->load($id);

    if (!$server) {
      return [
        'success' => FALSE,
        'error' => "Server '$id' not found.",
        'code' => 'NOT_FOUND',
      ];
    }

    /** @var \Drupal\search_api\ServerInterface $server */
    $backend = $server->getBackend();
    $backendConfig = $backend->getConfiguration();

    // Get indexes using this server.
    $indexStorage = $this->entityTypeManager->getStorage('search_api_index');
    $indexes = $indexStorage->loadByProperties(['server' => $id]);
    $indexList = [];
    foreach ($indexes as $index) {
      /** @var \Drupal\search_api\IndexInterface $index */
      $indexList[] = [
        'id' => $index->id(),
        'label' => $index->label(),
        'status' => $index->status() ? 'enabled' : 'disabled',
      ];
    }

    // Check if server is available.
    $available = FALSE;
    try {
      $available = $server->isAvailable();
    }
    catch (\Exception $e) {
      // Server not available.
    }

    return [
      'success' => TRUE,
      'server' => [
        'id' => $server->id(),
        'label' => $server->label(),
        'description' => $server->getDescription(),
        'status' => $server->status() ? 'enabled' : 'disabled',
        'backend' => $server->getBackendId(),
        'backend_label' => $backend->label(),
        'available' => $available,
        'indexes' => $indexList,
        'index_count' => count($indexList),
      ],
    ];
  }

  /**
   * Search content using a Search API index.
   *
   * @param string $indexId
   *   The index ID to search.
   * @param string $keywords
   *   Search keywords.
   * @param array $filters
   *   Optional filters (field => value pairs).
   * @param int $limit
   *   Maximum results to return.
   * @param int $offset
   *   Pagination offset.
   *
   * @return array
   *   Search results with items and metadata.
   */
  public function search(string $indexId, string $keywords, array $filters = [], int $limit = 25, int $offset = 0): array {
    $index = $this->loadIndex($indexId);
    if (!$index) {
      return [
        'success' => FALSE,
        'error' => "Index '$indexId' not found.",
        'code' => 'NOT_FOUND',
      ];
    }

    if (!$index->status()) {
      return [
        'success' => FALSE,
        'error' => "Index '$indexId' is disabled.",
        'code' => 'INDEX_DISABLED',
      ];
    }

    try {
      $query = $index->query();

      // Set search keywords.
      if (!empty($keywords)) {
        $query->keys($keywords);
      }

      // Apply filters.
      foreach ($filters as $field => $value) {
        // Validate field name to prevent injection.
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $field)) {
          continue;
        }
        // Check if field exists in index.
        if ($index->getField($field)) {
          $query->addCondition($field, $value);
        }
      }

      // Set pagination.
      $query->range($offset, min($limit, 100));

      // Execute search.
      $results = $query->execute();

      $items = [];
      foreach ($results->getResultItems() as $resultItem) {
        $item = [
          'id' => $resultItem->getId(),
          'score' => $resultItem->getScore(),
        ];

        // Try to get the original entity.
        try {
          $entity = $resultItem->getOriginalObject()->getValue();
          if ($entity) {
            $item['entity_type'] = $entity->getEntityTypeId();
            $item['bundle'] = $entity->bundle();
            $item['entity_id'] = $entity->id();
            $item['label'] = $entity->label();
            $item['uuid'] = $entity->uuid();

            // Add URL if available.
            if ($entity->hasLinkTemplate('canonical')) {
              try {
                $item['url'] = $entity->toUrl('canonical')->toString();
              }
              catch (\Exception $e) {
                // URL generation failed, skip.
              }
            }
          }
        }
        catch (\Exception $e) {
          // Could not load entity, include basic info only.
        }

        // Include excerpt if available.
        $excerpt = $resultItem->getExcerpt();
        if ($excerpt) {
          $item['excerpt'] = strip_tags($excerpt);
        }

        $items[] = $item;
      }

      return [
        'success' => TRUE,
        'data' => [
          'items' => $items,
          'total' => $results->getResultCount(),
          'returned' => count($items),
          'limit' => $limit,
          'offset' => $offset,
          'has_more' => ($offset + count($items)) < $results->getResultCount(),
          'index' => $indexId,
          'keywords' => $keywords,
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Search failed: ' . $e->getMessage(),
        'code' => 'SEARCH_ERROR',
      ];
    }
  }

  /**
   * Load an index by ID.
   *
   * @param string $id
   *   The index ID.
   *
   * @return \Drupal\search_api\IndexInterface|null
   *   The index or null if not found.
   */
  protected function loadIndex(string $id): ?IndexInterface {
    $storage = $this->entityTypeManager->getStorage('search_api_index');
    $index = $storage->load($id);
    return $index instanceof IndexInterface ? $index : NULL;
  }

}
