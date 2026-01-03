<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for managing content types.
 */
class ContentTypeService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $bundleInfo,
    protected ConfigFactoryInterface $configFactory,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Create a new content type.
   *
   * @param string $id
   *   Machine name (lowercase, underscores).
   * @param string $label
   *   Human-readable name.
   * @param array $options
   *   Optional settings: description, help, new_revision, preview_mode,
   *   display_submitted, create_body.
   *
   * @return array
   *   Result with success status and data or error.
   */
  public function createContentType(string $id, string $label, array $options = []): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Validate machine name.
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $id)) {
      return [
        'success' => FALSE,
        'error' => 'Invalid machine name. Use lowercase letters, numbers, and underscores. Must start with a letter.',
      ];
    }

    if (strlen($id) > 32) {
      return [
        'success' => FALSE,
        'error' => 'Machine name must be 32 characters or less.',
      ];
    }

    // Check if content type already exists.
    $existing = $this->entityTypeManager->getStorage('node_type')->load($id);
    if ($existing) {
      return [
        'success' => FALSE,
        'error' => "Content type '$id' already exists.",
      ];
    }

    try {
      $nodeType = $this->entityTypeManager->getStorage('node_type')->create([
        'type' => $id,
        'name' => $label,
        'description' => $options['description'] ?? '',
        'help' => $options['help'] ?? '',
        'new_revision' => $options['new_revision'] ?? TRUE,
        'preview_mode' => $options['preview_mode'] ?? DRUPAL_OPTIONAL,
        'display_submitted' => $options['display_submitted'] ?? TRUE,
      ]);

      $nodeType->save();

      // Create body field if requested (default TRUE).
      if ($options['create_body'] ?? TRUE) {
        $this->createBodyField($id);
      }

      $this->auditLogger->logSuccess('create_content_type', 'node_type', $id, [
        'label' => $label,
        'options' => $options,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'label' => $label,
          'message' => "Content type '$label' ($id) created successfully.",
          'admin_path' => "/admin/structure/types/manage/$id",
          'add_content_path' => "/node/add/$id",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_content_type', 'node_type', $id, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to create content type: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Delete a content type.
   *
   * @param string $id
   *   Content type machine name.
   * @param bool $force
   *   If TRUE, delete even if content exists (dangerous!).
   *
   * @return array
   *   Result with success status.
   */
  public function deleteContentType(string $id, bool $force = FALSE): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $nodeType = $this->entityTypeManager->getStorage('node_type')->load($id);

    if (!$nodeType) {
      return [
        'success' => FALSE,
        'error' => "Content type '$id' not found.",
      ];
    }

    // Check for existing content.
    // SECURITY NOTE: accessCheck(FALSE) is intentional here.
    // This is a system-level count query for content type deletion safety.
    // We need to count ALL nodes of this type, not just those the current
    // user can access, to prevent accidental data loss.
    $contentCount = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $id)
      ->count()
      ->execute();

    if ($contentCount > 0 && !$force) {
      return [
        'success' => FALSE,
        'error' => "Content type '$id' has $contentCount nodes. Delete content first or use force=true (dangerous!).",
        'content_count' => (int) $contentCount,
      ];
    }

    try {
      $label = $nodeType->label();
      $nodeType->delete();

      $this->auditLogger->logSuccess('delete_content_type', 'node_type', $id, [
        'label' => $label,
        'force' => $force,
        'deleted_content' => $force ? (int) $contentCount : 0,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'message' => "Content type '$label' ($id) deleted successfully.",
          'deleted_content' => $force ? (int) $contentCount : 0,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_content_type', 'node_type', $id, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to delete content type: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Create the body field for a content type.
   *
   * @param string $bundle
   *   The content type ID.
   */
  protected function createBodyField(string $bundle): void {
    // Check if body field storage exists.
    $fieldStorage = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->load('node.body');

    if (!$fieldStorage) {
      // Create field storage.
      $fieldStorage = $this->entityTypeManager
        ->getStorage('field_storage_config')
        ->create([
          'field_name' => 'body',
          'entity_type' => 'node',
          'type' => 'text_with_summary',
          'cardinality' => 1,
        ]);
      $fieldStorage->save();
    }

    // Create field instance.
    $field = $this->entityTypeManager
      ->getStorage('field_config')
      ->create([
        'field_storage' => $fieldStorage,
        'bundle' => $bundle,
        'label' => 'Body',
        'settings' => [
          'display_summary' => TRUE,
        ],
      ]);
    $field->save();

    // Set form display.
    $formDisplay = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load('node.' . $bundle . '.default');

    if (!$formDisplay) {
      $formDisplay = $this->entityTypeManager
        ->getStorage('entity_form_display')
        ->create([
          'targetEntityType' => 'node',
          'bundle' => $bundle,
          'mode' => 'default',
          'status' => TRUE,
        ]);
    }

    $formDisplay->setComponent('body', [
      'type' => 'text_textarea_with_summary',
      'weight' => 0,
    ])->save();

    // Set view display.
    $viewDisplay = $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->load('node.' . $bundle . '.default');

    if (!$viewDisplay) {
      $viewDisplay = $this->entityTypeManager
        ->getStorage('entity_view_display')
        ->create([
          'targetEntityType' => 'node',
          'bundle' => $bundle,
          'mode' => 'default',
          'status' => TRUE,
        ]);
    }

    $viewDisplay->setComponent('body', [
      'type' => 'text_default',
      'weight' => 0,
      'label' => 'hidden',
    ])->save();
  }

}
