<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_content\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for content CRUD operations.
 */
class ContentService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected AccountProxyInterface $currentUser,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Create new content.
   */
  public function createContent(string $type, string $title, array $fields = [], array $options = []): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $nodeType = $this->entityTypeManager->getStorage('node_type')->load($type);
    if (!$nodeType) {
      return ['success' => FALSE, 'error' => "Content type '$type' not found. Use mcp_structure_list_content_types to see available types."];
    }

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $type);

    try {
      $nodeData = [
        'type' => $type,
        'title' => $title,
        'uid' => $options['uid'] ?? $this->currentUser->id(),
        'status' => $options['status'] ?? 0,
      ];

      foreach ($fields as $fieldName => $value) {
        if (!str_starts_with($fieldName, 'field_') && !in_array($fieldName, ['body'])) {
          $checkName = 'field_' . $fieldName;
          if (isset($fieldDefinitions[$checkName])) {
            $fieldName = $checkName;
          }
        }
        $nodeData[$fieldName] = $this->normalizeFieldValue($fieldName, $value, $fieldDefinitions);
      }

      $node = $this->entityTypeManager->getStorage('node')->create($nodeData);
      $node->save();

      $this->auditLogger->logSuccess('create_content', 'node', (string) $node->id(), [
        'title' => $title,
        'type' => $type,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'nid' => $node->id(),
          'uuid' => $node->uuid(),
          'title' => $title,
          'type' => $type,
          'status' => $node->isPublished() ? 'published' : 'draft',
          'url' => $node->toUrl()->toString(),
          'message' => "Content '$title' created successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_content', 'node', 'new', ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to create content: ' . $e->getMessage()];
    }
  }

  /**
   * Update existing content.
   */
  public function updateContent(int $nid, array $updates): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node) {
      return ['success' => FALSE, 'error' => "Content with ID $nid not found. Use mcp_content_search to find content by title or mcp_content_list to browse."];
    }

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $node->bundle());

    try {
      foreach ($updates as $fieldName => $value) {
        if ($fieldName === 'title') {
          $node->setTitle($value);
          continue;
        }
        if ($fieldName === 'status') {
          $value ? $node->setPublished() : $node->setUnpublished();
          continue;
        }

        if (!str_starts_with($fieldName, 'field_') && !in_array($fieldName, ['body'])) {
          $checkName = 'field_' . $fieldName;
          if (isset($fieldDefinitions[$checkName])) {
            $fieldName = $checkName;
          }
        }

        if ($node->hasField($fieldName)) {
          $node->set($fieldName, $this->normalizeFieldValue($fieldName, $value, $fieldDefinitions));
        }
      }

      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage('Updated via MCP Tools');
      $node->save();

      $this->auditLogger->logSuccess('update_content', 'node', (string) $nid, ['updates' => array_keys($updates)]);

      return [
        'success' => TRUE,
        'data' => [
          'nid' => $nid,
          'title' => $node->getTitle(),
          'revision_id' => $node->getRevisionId(),
          'message' => "Content updated successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('update_content', 'node', (string) $nid, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to update content: ' . $e->getMessage()];
    }
  }

  /**
   * Delete content.
   */
  public function deleteContent(int $nid): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node) {
      return ['success' => FALSE, 'error' => "Content with ID $nid not found. Use mcp_content_search to find content by title or mcp_content_list to browse."];
    }

    try {
      $title = $node->getTitle();
      $type = $node->bundle();
      $node->delete();

      $this->auditLogger->logSuccess('delete_content', 'node', (string) $nid, ['title' => $title, 'type' => $type]);

      return [
        'success' => TRUE,
        'data' => [
          'nid' => $nid,
          'title' => $title,
          'type' => $type,
          'message' => "Content '$title' deleted successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_content', 'node', (string) $nid, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to delete content: ' . $e->getMessage()];
    }
  }

  /**
   * Publish or unpublish content.
   */
  public function setPublishStatus(int $nid, bool $publish): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node) {
      return ['success' => FALSE, 'error' => "Content with ID $nid not found. Use mcp_content_search to find content by title or mcp_content_list to browse."];
    }

    if ($publish === $node->isPublished()) {
      return [
        'success' => TRUE,
        'data' => [
          'nid' => $nid,
          'status' => $publish ? 'published' : 'unpublished',
          'message' => 'Content was already ' . ($publish ? 'published' : 'unpublished') . '.',
          'changed' => FALSE,
        ],
      ];
    }

    try {
      $publish ? $node->setPublished() : $node->setUnpublished();
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage(($publish ? 'Published' : 'Unpublished') . ' via MCP Tools');
      $node->save();

      $this->auditLogger->logSuccess($publish ? 'publish_content' : 'unpublish_content', 'node', (string) $nid, []);

      return [
        'success' => TRUE,
        'data' => [
          'nid' => $nid,
          'title' => $node->getTitle(),
          'status' => $publish ? 'published' : 'unpublished',
          'message' => "Content '" . $node->getTitle() . "' " . ($publish ? 'published' : 'unpublished') . '.',
          'changed' => TRUE,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to change publish status: ' . $e->getMessage()];
    }
  }

  /**
   * Normalize field value based on field type.
   */
  protected function normalizeFieldValue(string $fieldName, mixed $value, array $fieldDefinitions): mixed {
    if (!isset($fieldDefinitions[$fieldName])) {
      return $value;
    }

    $fieldType = $fieldDefinitions[$fieldName]->getType();

    return match ($fieldType) {
      'text_long', 'text_with_summary' => is_array($value) ? $value : ['value' => $value, 'format' => 'basic_html'],
      'entity_reference' => is_array($value) ? $value : ['target_id' => $value],
      'image', 'file' => is_array($value) ? $value : ['target_id' => $value],
      'link' => is_array($value) ? $value : ['uri' => $value],
      'datetime' => is_array($value) ? $value : ['value' => $value],
      default => $value,
    };
  }

}
