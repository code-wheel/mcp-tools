<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for analyzing content.
 */
class ContentAnalysisService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected Connection $database,
  ) {}

  /**
   * Get list of content types with field information.
   *
   * @return array
   *   Array of content types.
   */
  public function getContentTypes(): array {
    $nodeTypes = $this->entityTypeManager
      ->getStorage('node_type')
      ->loadMultiple();

    $types = [];
    foreach ($nodeTypes as $type) {
      $fields = $this->entityFieldManager->getFieldDefinitions('node', $type->id());

      $customFields = [];
      foreach ($fields as $name => $definition) {
        // Skip base fields.
        if ($definition->getFieldStorageDefinition()->isBaseField()) {
          continue;
        }
        $customFields[] = [
          'name' => $name,
          'label' => $definition->getLabel(),
          'type' => $definition->getType(),
          'required' => $definition->isRequired(),
        ];
      }

      $types[] = [
        'id' => $type->id(),
        'label' => $type->label(),
        'description' => $type->getDescription(),
        'field_count' => count($customFields),
        'fields' => $customFields,
      ];
    }

    return [
      'total_types' => count($types),
      'types' => $types,
    ];
  }

  /**
   * Get recently created or updated content.
   *
   * @param int $limit
   *   Maximum items to return.
   * @param string|null $type
   *   Filter by content type.
   * @param string $sort
   *   Sort by 'created' or 'changed'.
   *
   * @return array
   *   Array of recent content.
   */
  public function getRecentContent(int $limit = 20, ?string $type = NULL, string $sort = 'changed'): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->sort($sort, 'DESC')
      ->range(0, $limit);

    if ($type !== NULL) {
      $query->condition('type', $type);
    }

    $nids = $query->execute();

    if (empty($nids)) {
      return [
        'total' => 0,
        'content' => [],
      ];
    }

    $nodes = $storage->loadMultiple($nids);
    $content = [];

    foreach ($nodes as $node) {
      $content[] = [
        'id' => $node->id(),
        'uuid' => $node->uuid(),
        'title' => $node->getTitle(),
        'type' => $node->bundle(),
        'status' => $node->isPublished() ? 'published' : 'unpublished',
        'created' => date('Y-m-d H:i:s', $node->getCreatedTime()),
        'changed' => date('Y-m-d H:i:s', $node->getChangedTime()),
        'author' => $node->getOwner()?->getDisplayName() ?? 'Unknown',
        'url' => $node->toUrl()->toString(),
      ];
    }

    return [
      'total' => count($content),
      'sorted_by' => $sort,
      'content' => $content,
    ];
  }

  /**
   * Search content by text.
   *
   * @param string $query
   *   Search query.
   * @param int $limit
   *   Maximum results.
   * @param string|null $type
   *   Filter by content type.
   *
   * @return array
   *   Search results.
   */
  public function searchContent(string $query, int $limit = 20, ?string $type = NULL): array {
    if (strlen($query) < 3) {
      return [
        'error' => 'Search query must be at least 3 characters.',
        'results' => [],
      ];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $entityQuery = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('title', '%' . $query . '%', 'LIKE')
      ->sort('changed', 'DESC')
      ->range(0, $limit);

    if ($type !== NULL) {
      $entityQuery->condition('type', $type);
    }

    $nids = $entityQuery->execute();

    if (empty($nids)) {
      return [
        'query' => $query,
        'total' => 0,
        'results' => [],
      ];
    }

    $nodes = $storage->loadMultiple($nids);
    $results = [];

    foreach ($nodes as $node) {
      $results[] = [
        'id' => $node->id(),
        'uuid' => $node->uuid(),
        'title' => $node->getTitle(),
        'type' => $node->bundle(),
        'status' => $node->isPublished() ? 'published' : 'unpublished',
        'changed' => date('Y-m-d H:i:s', $node->getChangedTime()),
        'url' => $node->toUrl()->toString(),
      ];
    }

    return [
      'query' => $query,
      'total' => count($results),
      'results' => $results,
    ];
  }

  /**
   * Get content by ID.
   *
   * @param int $id
   *   Node ID.
   *
   * @return array
   *   Content data or error.
   */
  public function getContentById(int $id): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $node = $storage->load($id);

    if (!$node) {
      return [
        'error' => "Content with ID $id not found.",
      ];
    }

    // Get field values.
    $fields = [];
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $node->bundle());

    foreach ($fieldDefinitions as $name => $definition) {
      if ($definition->getFieldStorageDefinition()->isBaseField()) {
        continue;
      }

      $value = $node->get($name)->getValue();
      $fields[$name] = [
        'label' => (string) $definition->getLabel(),
        'type' => $definition->getType(),
        'value' => $this->simplifyFieldValue($value, $definition->getType()),
      ];
    }

    return [
      'id' => $node->id(),
      'uuid' => $node->uuid(),
      'title' => $node->getTitle(),
      'type' => $node->bundle(),
      'status' => $node->isPublished() ? 'published' : 'unpublished',
      'created' => date('Y-m-d H:i:s', $node->getCreatedTime()),
      'changed' => date('Y-m-d H:i:s', $node->getChangedTime()),
      'author' => $node->getOwner()?->getDisplayName() ?? 'Unknown',
      'url' => $node->toUrl()->toString(),
      'fields' => $fields,
    ];
  }

  /**
   * Simplify field values for output.
   *
   * @param array $value
   *   Raw field value.
   * @param string $type
   *   Field type.
   *
   * @return mixed
   *   Simplified value.
   */
  protected function simplifyFieldValue(array $value, string $type): mixed {
    if (empty($value)) {
      return NULL;
    }

    // Handle common field types.
    return match ($type) {
      'string', 'string_long' => $value[0]['value'] ?? NULL,
      'text', 'text_long', 'text_with_summary' => $value[0]['value'] ?? NULL,
      'boolean' => (bool) ($value[0]['value'] ?? FALSE),
      'integer', 'decimal', 'float' => $value[0]['value'] ?? NULL,
      'entity_reference' => array_column($value, 'target_id'),
      'image', 'file' => array_column($value, 'target_id'),
      'link' => array_map(fn($v) => ['uri' => $v['uri'] ?? '', 'title' => $v['title'] ?? ''], $value),
      'datetime' => $value[0]['value'] ?? NULL,
      default => $value,
    };
  }

}
