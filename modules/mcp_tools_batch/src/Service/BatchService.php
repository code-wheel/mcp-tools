<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_batch\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

/**
 * Service for batch/bulk operations.
 */
class BatchService {

  /**
   * Maximum items per batch operation.
   */
  public const BATCH_LIMIT = 50;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected AccountProxyInterface $currentUser,
    protected ModuleHandlerInterface $moduleHandler,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Create multiple content items at once.
   *
   * @param string $contentType
   *   The content type machine name.
   * @param array $items
   *   Array of items to create. Each item should have 'title' and optionally 'fields'.
   *
   * @return array
   *   Result with created items and any errors.
   */
  public function createMultipleContent(string $contentType, array $items): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    if (empty($items)) {
      return ['success' => FALSE, 'error' => 'No items provided for batch creation.'];
    }

    if (count($items) > self::BATCH_LIMIT) {
      return [
        'success' => FALSE,
        'error' => sprintf('Batch limit exceeded. Maximum %d items allowed per operation.', self::BATCH_LIMIT),
      ];
    }

    // Validate content type exists.
    $nodeType = $this->entityTypeManager->getStorage('node_type')->load($contentType);
    if (!$nodeType) {
      return ['success' => FALSE, 'error' => "Content type '$contentType' not found."];
    }

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $contentType);

    $created = [];
    $errors = [];

    foreach ($items as $index => $item) {
      $title = $item['title'] ?? '';
      if (empty($title)) {
        $errors[] = [
          'index' => $index,
          'error' => 'Title is required.',
          'data' => $item,
        ];
        continue;
      }

      try {
        $nodeData = [
          'type' => $contentType,
          'title' => $title,
          'uid' => $item['uid'] ?? $this->currentUser->id(),
          'status' => $item['status'] ?? 0,
        ];

        // Process fields.
        if (!empty($item['fields'])) {
          foreach ($item['fields'] as $fieldName => $value) {
            if (!str_starts_with($fieldName, 'field_') && !in_array($fieldName, ['body'])) {
              $checkName = 'field_' . $fieldName;
              if (isset($fieldDefinitions[$checkName])) {
                $fieldName = $checkName;
              }
            }
            $nodeData[$fieldName] = $this->normalizeFieldValue($fieldName, $value, $fieldDefinitions);
          }
        }

        $node = Node::create($nodeData);
        $node->save();

        $created[] = [
          'index' => $index,
          'nid' => $node->id(),
          'uuid' => $node->uuid(),
          'title' => $title,
          'url' => $node->toUrl()->toString(),
        ];
      }
      catch (\Exception $e) {
        $errors[] = [
          'index' => $index,
          'error' => $e->getMessage(),
          'data' => $item,
        ];
      }
    }

    $this->auditLogger->logSuccess('batch_create_content', 'node', 'bulk', [
      'content_type' => $contentType,
      'total_requested' => count($items),
      'created' => count($created),
      'errors' => count($errors),
    ]);

    return [
      'success' => TRUE,
      'data' => [
        'content_type' => $contentType,
        'total_requested' => count($items),
        'created_count' => count($created),
        'error_count' => count($errors),
        'created' => $created,
        'errors' => $errors,
        'message' => sprintf('Batch create complete: %d created, %d errors.', count($created), count($errors)),
      ],
    ];
  }

  /**
   * Update multiple content items at once.
   *
   * @param array $updates
   *   Array of updates. Each should have 'id' (nid) and 'fields' to update.
   *
   * @return array
   *   Result with updated items and any errors.
   */
  public function updateMultipleContent(array $updates): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    if (empty($updates)) {
      return ['success' => FALSE, 'error' => 'No updates provided.'];
    }

    if (count($updates) > self::BATCH_LIMIT) {
      return [
        'success' => FALSE,
        'error' => sprintf('Batch limit exceeded. Maximum %d items allowed per operation.', self::BATCH_LIMIT),
      ];
    }

    $updated = [];
    $errors = [];

    foreach ($updates as $index => $update) {
      $nid = $update['id'] ?? $update['nid'] ?? NULL;
      if (empty($nid)) {
        $errors[] = [
          'index' => $index,
          'error' => 'Node ID (id or nid) is required.',
          'data' => $update,
        ];
        continue;
      }

      $fields = $update['fields'] ?? [];
      if (empty($fields)) {
        $errors[] = [
          'index' => $index,
          'error' => 'At least one field to update is required.',
          'data' => $update,
        ];
        continue;
      }

      try {
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
        if (!$node) {
          $errors[] = [
            'index' => $index,
            'error' => "Node with ID $nid not found.",
            'data' => $update,
          ];
          continue;
        }

        $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $node->bundle());

        foreach ($fields as $fieldName => $value) {
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
        $node->setRevisionLogMessage('Batch updated via MCP Tools');
        $node->save();

        $updated[] = [
          'index' => $index,
          'nid' => (int) $nid,
          'title' => $node->getTitle(),
          'revision_id' => $node->getRevisionId(),
        ];
      }
      catch (\Exception $e) {
        $errors[] = [
          'index' => $index,
          'error' => $e->getMessage(),
          'data' => $update,
        ];
      }
    }

    $this->auditLogger->logSuccess('batch_update_content', 'node', 'bulk', [
      'total_requested' => count($updates),
      'updated' => count($updated),
      'errors' => count($errors),
    ]);

    return [
      'success' => TRUE,
      'data' => [
        'total_requested' => count($updates),
        'updated_count' => count($updated),
        'error_count' => count($errors),
        'updated' => $updated,
        'errors' => $errors,
        'message' => sprintf('Batch update complete: %d updated, %d errors.', count($updated), count($errors)),
      ],
    ];
  }

  /**
   * Delete multiple content items at once.
   *
   * @param array $ids
   *   Array of node IDs to delete.
   * @param bool $force
   *   If TRUE, delete even published content. Default FALSE.
   *
   * @return array
   *   Result with deleted items and any errors.
   */
  public function deleteMultipleContent(array $ids, bool $force = FALSE): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    if (empty($ids)) {
      return ['success' => FALSE, 'error' => 'No node IDs provided for deletion.'];
    }

    if (count($ids) > self::BATCH_LIMIT) {
      return [
        'success' => FALSE,
        'error' => sprintf('Batch limit exceeded. Maximum %d items allowed per operation.', self::BATCH_LIMIT),
      ];
    }

    $deleted = [];
    $skipped = [];
    $errors = [];

    foreach ($ids as $index => $nid) {
      try {
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
        if (!$node) {
          $errors[] = [
            'index' => $index,
            'nid' => $nid,
            'error' => 'Node not found.',
          ];
          continue;
        }

        // Safety check: don't delete published content unless forced.
        if (!$force && $node->isPublished()) {
          $skipped[] = [
            'index' => $index,
            'nid' => (int) $nid,
            'title' => $node->getTitle(),
            'reason' => 'Node is published. Use force=true to delete published content.',
          ];
          continue;
        }

        $title = $node->getTitle();
        $type = $node->bundle();
        $node->delete();

        $deleted[] = [
          'index' => $index,
          'nid' => (int) $nid,
          'title' => $title,
          'type' => $type,
        ];
      }
      catch (\Exception $e) {
        $errors[] = [
          'index' => $index,
          'nid' => $nid,
          'error' => $e->getMessage(),
        ];
      }
    }

    $this->auditLogger->logSuccess('batch_delete_content', 'node', 'bulk', [
      'total_requested' => count($ids),
      'deleted' => count($deleted),
      'skipped' => count($skipped),
      'errors' => count($errors),
      'force' => $force,
    ]);

    return [
      'success' => TRUE,
      'data' => [
        'total_requested' => count($ids),
        'deleted_count' => count($deleted),
        'skipped_count' => count($skipped),
        'error_count' => count($errors),
        'deleted' => $deleted,
        'skipped' => $skipped,
        'errors' => $errors,
        'message' => sprintf(
          'Batch delete complete: %d deleted, %d skipped, %d errors.',
          count($deleted),
          count($skipped),
          count($errors)
        ),
      ],
    ];
  }

  /**
   * Publish or unpublish multiple content items.
   *
   * @param array $ids
   *   Array of node IDs.
   * @param bool $publish
   *   TRUE to publish, FALSE to unpublish.
   *
   * @return array
   *   Result with processed items and any errors.
   */
  public function publishMultiple(array $ids, bool $publish = TRUE): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    if (empty($ids)) {
      return ['success' => FALSE, 'error' => 'No node IDs provided.'];
    }

    if (count($ids) > self::BATCH_LIMIT) {
      return [
        'success' => FALSE,
        'error' => sprintf('Batch limit exceeded. Maximum %d items allowed per operation.', self::BATCH_LIMIT),
      ];
    }

    $processed = [];
    $unchanged = [];
    $errors = [];
    $action = $publish ? 'published' : 'unpublished';

    foreach ($ids as $index => $nid) {
      try {
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
        if (!$node) {
          $errors[] = [
            'index' => $index,
            'nid' => $nid,
            'error' => 'Node not found.',
          ];
          continue;
        }

        // Check if already in desired state.
        if ($publish === $node->isPublished()) {
          $unchanged[] = [
            'index' => $index,
            'nid' => (int) $nid,
            'title' => $node->getTitle(),
            'status' => $action,
          ];
          continue;
        }

        $publish ? $node->setPublished() : $node->setUnpublished();
        $node->setNewRevision(TRUE);
        $node->setRevisionLogMessage(ucfirst($action) . ' via MCP Tools batch operation');
        $node->save();

        $processed[] = [
          'index' => $index,
          'nid' => (int) $nid,
          'title' => $node->getTitle(),
          'status' => $action,
        ];
      }
      catch (\Exception $e) {
        $errors[] = [
          'index' => $index,
          'nid' => $nid,
          'error' => $e->getMessage(),
        ];
      }
    }

    $this->auditLogger->logSuccess('batch_publish_content', 'node', 'bulk', [
      'action' => $action,
      'total_requested' => count($ids),
      'processed' => count($processed),
      'unchanged' => count($unchanged),
      'errors' => count($errors),
    ]);

    return [
      'success' => TRUE,
      'data' => [
        'action' => $action,
        'total_requested' => count($ids),
        'processed_count' => count($processed),
        'unchanged_count' => count($unchanged),
        'error_count' => count($errors),
        'processed' => $processed,
        'unchanged' => $unchanged,
        'errors' => $errors,
        'message' => sprintf(
          'Batch %s complete: %d %s, %d unchanged, %d errors.',
          $publish ? 'publish' : 'unpublish',
          count($processed),
          $action,
          count($unchanged),
          count($errors)
        ),
      ],
    ];
  }

  /**
   * Assign a role to multiple users.
   *
   * @param string $role
   *   The role machine name to assign.
   * @param array $userIds
   *   Array of user IDs.
   *
   * @return array
   *   Result with processed users and any errors.
   */
  public function assignRoleToUsers(string $role, array $userIds): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    if (empty($role)) {
      return ['success' => FALSE, 'error' => 'Role is required.'];
    }

    // Block assigning administrator role.
    if ($role === 'administrator') {
      return [
        'success' => FALSE,
        'error' => "The 'administrator' role cannot be assigned via MCP batch operations.",
      ];
    }

    if (empty($userIds)) {
      return ['success' => FALSE, 'error' => 'No user IDs provided.'];
    }

    if (count($userIds) > self::BATCH_LIMIT) {
      return [
        'success' => FALSE,
        'error' => sprintf('Batch limit exceeded. Maximum %d items allowed per operation.', self::BATCH_LIMIT),
      ];
    }

    // Verify role exists.
    $roleEntity = $this->entityTypeManager->getStorage('user_role')->load($role);
    if (!$roleEntity) {
      return ['success' => FALSE, 'error' => "Role '$role' not found."];
    }

    $assigned = [];
    $already_has = [];
    $errors = [];

    foreach ($userIds as $index => $uid) {
      // Skip uid 1 (super admin).
      if ((int) $uid === 1) {
        $errors[] = [
          'index' => $index,
          'uid' => $uid,
          'error' => 'Cannot modify the super admin user (uid 1).',
        ];
        continue;
      }

      try {
        $user = $this->entityTypeManager->getStorage('user')->load($uid);
        if (!$user) {
          $errors[] = [
            'index' => $index,
            'uid' => $uid,
            'error' => 'User not found.',
          ];
          continue;
        }

        // Check if user already has the role.
        if ($user->hasRole($role)) {
          $already_has[] = [
            'index' => $index,
            'uid' => (int) $uid,
            'username' => $user->getAccountName(),
          ];
          continue;
        }

        $user->addRole($role);
        $user->save();

        $assigned[] = [
          'index' => $index,
          'uid' => (int) $uid,
          'username' => $user->getAccountName(),
        ];
      }
      catch (\Exception $e) {
        $errors[] = [
          'index' => $index,
          'uid' => $uid,
          'error' => $e->getMessage(),
        ];
      }
    }

    $this->auditLogger->logSuccess('batch_assign_role', 'user', 'bulk', [
      'role' => $role,
      'total_requested' => count($userIds),
      'assigned' => count($assigned),
      'already_had' => count($already_has),
      'errors' => count($errors),
    ]);

    return [
      'success' => TRUE,
      'data' => [
        'role' => $role,
        'total_requested' => count($userIds),
        'assigned_count' => count($assigned),
        'already_had_count' => count($already_has),
        'error_count' => count($errors),
        'assigned' => $assigned,
        'already_had' => $already_has,
        'errors' => $errors,
        'message' => sprintf(
          "Batch role assignment complete: %d assigned '%s', %d already had role, %d errors.",
          count($assigned),
          $role,
          count($already_has),
          count($errors)
        ),
      ],
    ];
  }

  /**
   * Create multiple taxonomy terms at once.
   *
   * @param string $vocabulary
   *   The vocabulary machine name.
   * @param array $terms
   *   Array of terms to create. Each can be a string (term name) or array with 'name', 'description', 'parent', 'weight'.
   *
   * @return array
   *   Result with created terms and any errors.
   */
  public function createMultipleTerms(string $vocabulary, array $terms): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    if (empty($vocabulary)) {
      return ['success' => FALSE, 'error' => 'Vocabulary is required.'];
    }

    if (empty($terms)) {
      return ['success' => FALSE, 'error' => 'No terms provided for batch creation.'];
    }

    if (count($terms) > self::BATCH_LIMIT) {
      return [
        'success' => FALSE,
        'error' => sprintf('Batch limit exceeded. Maximum %d items allowed per operation.', self::BATCH_LIMIT),
      ];
    }

    // Verify vocabulary exists.
    $vocab = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($vocabulary);
    if (!$vocab) {
      return ['success' => FALSE, 'error' => "Vocabulary '$vocabulary' not found."];
    }

    $created = [];
    $skipped = [];
    $errors = [];

    foreach ($terms as $index => $termData) {
      // Normalize to array format.
      if (is_string($termData)) {
        $termData = ['name' => $termData];
      }

      $name = $termData['name'] ?? '';
      if (empty($name)) {
        $errors[] = [
          'index' => $index,
          'error' => 'Term name is required.',
          'data' => $termData,
        ];
        continue;
      }

      // Check for existing term with same name.
      // SECURITY NOTE: accessCheck(FALSE) is intentional here.
      // This is a system-level duplicate check - we need to prevent
      // duplicates regardless of the current user's access permissions.
      $existing = $this->entityTypeManager->getStorage('taxonomy_term')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('vid', $vocabulary)
        ->condition('name', $name)
        ->execute();

      if (!empty($existing)) {
        $skipped[] = [
          'index' => $index,
          'name' => $name,
          'reason' => 'Term already exists.',
          'existing_tid' => reset($existing),
        ];
        continue;
      }

      try {
        $termValues = [
          'vid' => $vocabulary,
          'name' => $name,
          'description' => [
            'value' => $termData['description'] ?? '',
            'format' => 'basic_html',
          ],
          'weight' => $termData['weight'] ?? 0,
        ];

        if (isset($termData['parent'])) {
          $termValues['parent'] = ['target_id' => $termData['parent']];
        }

        $term = Term::create($termValues);
        $term->save();

        $created[] = [
          'index' => $index,
          'tid' => $term->id(),
          'name' => $name,
        ];
      }
      catch (\Exception $e) {
        $errors[] = [
          'index' => $index,
          'error' => $e->getMessage(),
          'data' => $termData,
        ];
      }
    }

    $this->auditLogger->logSuccess('batch_create_terms', 'taxonomy_term', 'bulk', [
      'vocabulary' => $vocabulary,
      'total_requested' => count($terms),
      'created' => count($created),
      'skipped' => count($skipped),
      'errors' => count($errors),
    ]);

    return [
      'success' => TRUE,
      'data' => [
        'vocabulary' => $vocabulary,
        'total_requested' => count($terms),
        'created_count' => count($created),
        'skipped_count' => count($skipped),
        'error_count' => count($errors),
        'created' => $created,
        'skipped' => $skipped,
        'errors' => $errors,
        'message' => sprintf(
          'Batch term creation complete: %d created, %d skipped, %d errors.',
          count($created),
          count($skipped),
          count($errors)
        ),
      ],
    ];
  }

  /**
   * Create multiple redirects at once.
   *
   * @param array $redirects
   *   Array of redirects. Each should have 'source', 'destination', optional 'status_code', 'language'.
   *
   * @return array
   *   Result with created redirects and any errors.
   */
  public function createMultipleRedirects(array $redirects): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Check if redirect module is enabled.
    if (!$this->moduleHandler->moduleExists('redirect')) {
      return [
        'success' => FALSE,
        'error' => 'The Redirect module is not enabled. Please enable it first.',
      ];
    }

    if (empty($redirects)) {
      return ['success' => FALSE, 'error' => 'No redirects provided for batch creation.'];
    }

    if (count($redirects) > self::BATCH_LIMIT) {
      return [
        'success' => FALSE,
        'error' => sprintf('Batch limit exceeded. Maximum %d items allowed per operation.', self::BATCH_LIMIT),
      ];
    }

    $validStatusCodes = [301, 302, 303, 307];
    $created = [];
    $skipped = [];
    $errors = [];

    foreach ($redirects as $index => $redirectData) {
      $source = $redirectData['source'] ?? '';
      $destination = $redirectData['destination'] ?? '';
      $statusCode = $redirectData['status_code'] ?? 301;
      $language = $redirectData['language'] ?? NULL;

      // Validate required fields.
      if (empty($source)) {
        $errors[] = [
          'index' => $index,
          'error' => 'Source path is required.',
          'data' => $redirectData,
        ];
        continue;
      }

      if (empty($destination)) {
        $errors[] = [
          'index' => $index,
          'error' => 'Destination is required.',
          'data' => $redirectData,
        ];
        continue;
      }

      if (!in_array($statusCode, $validStatusCodes)) {
        $errors[] = [
          'index' => $index,
          'error' => "Invalid status code '$statusCode'. Must be one of: " . implode(', ', $validStatusCodes),
          'data' => $redirectData,
        ];
        continue;
      }

      // Normalize source path.
      $source = ltrim($source, '/');

      // Check for existing redirect.
      $storage = $this->entityTypeManager->getStorage('redirect');
      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('redirect_source__path', $source);

      if ($language) {
        $query->condition('language', $language);
      }

      $existingIds = $query->execute();

      if (!empty($existingIds)) {
        $skipped[] = [
          'index' => $index,
          'source' => $source,
          'reason' => 'Redirect already exists for this source.',
          'existing_id' => reset($existingIds),
        ];
        continue;
      }

      try {
        $redirect = $storage->create([
          'redirect_source' => [
            'path' => $source,
            'query' => [],
          ],
          'redirect_redirect' => [
            'uri' => $this->normalizeRedirectDestination($destination),
          ],
          'status_code' => $statusCode,
          'language' => $language ?? 'und',
        ]);
        $redirect->save();

        $created[] = [
          'index' => $index,
          'id' => $redirect->id(),
          'source' => $source,
          'destination' => $destination,
          'status_code' => $statusCode,
        ];
      }
      catch (\Exception $e) {
        $errors[] = [
          'index' => $index,
          'error' => $e->getMessage(),
          'data' => $redirectData,
        ];
      }
    }

    $this->auditLogger->logSuccess('batch_create_redirects', 'redirect', 'bulk', [
      'total_requested' => count($redirects),
      'created' => count($created),
      'skipped' => count($skipped),
      'errors' => count($errors),
    ]);

    return [
      'success' => TRUE,
      'data' => [
        'total_requested' => count($redirects),
        'created_count' => count($created),
        'skipped_count' => count($skipped),
        'error_count' => count($errors),
        'created' => $created,
        'skipped' => $skipped,
        'errors' => $errors,
        'message' => sprintf(
          'Batch redirect creation complete: %d created, %d skipped, %d errors.',
          count($created),
          count($skipped),
          count($errors)
        ),
      ],
    ];
  }

  /**
   * Normalize field value based on field type.
   *
   * @param string $fieldName
   *   The field name.
   * @param mixed $value
   *   The field value.
   * @param array $fieldDefinitions
   *   The field definitions.
   *
   * @return mixed
   *   The normalized value.
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

  /**
   * Normalize redirect destination to proper URI format.
   *
   * @param string $destination
   *   The destination path or URL.
   *
   * @return string
   *   Normalized URI.
   */
  protected function normalizeRedirectDestination(string $destination): string {
    // If it's already a full URL with scheme, return as-is.
    if (preg_match('#^https?://#', $destination)) {
      return $destination;
    }

    // If it starts with entity: or internal:, return as-is.
    if (preg_match('#^(entity|internal|route|base):#', $destination)) {
      return $destination;
    }

    // For internal paths, use internal: scheme.
    $destination = '/' . ltrim($destination, '/');
    return 'internal:' . $destination;
  }

}
