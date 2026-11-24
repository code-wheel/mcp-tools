<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_paragraphs\Service;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools\Service\FieldInput;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Service for managing Paragraphs types and fields.
 */
class ParagraphsService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
    protected FieldTypePluginManagerInterface $fieldTypeManager,
    protected EntityDisplayRepositoryInterface $displays,
  ) {}

  /**
   * List all paragraph types with their fields.
   *
   * @return array
   *   List of paragraph types with field information.
   */
  public function listParagraphTypes(): array {
    $types = [];

    $paragraphTypes = $this->entityTypeManager
      ->getStorage('paragraphs_type')
      ->loadMultiple();

    foreach ($paragraphTypes as $type) {
      $fields = $this->getFieldsForBundle($type->id());

      $types[] = [
        'id' => $type->id(),
        'label' => $type->label(),
        'description' => $type->getDescription() ?? '',
        'icon_uuid' => ($type->getIconFile() ?: NULL)?->uuid(),
        'field_count' => count($fields),
        'fields' => array_map(fn($f) => [
          'name' => $f['name'],
          'type' => $f['type'],
          'label' => $f['label'],
        ], $fields),
      ];
    }

    return [
      'success' => TRUE,
      'data' => [
        'types' => $types,
        'total' => count($types),
      ],
    ];
  }

  /**
   * Get details of a specific paragraph type.
   *
   * @param string $id
   *   The paragraph type machine name.
   *
   * @return array
   *   Paragraph type details or error.
   */
  public function getParagraphType(string $id): array {
    $paragraphType = $this->entityTypeManager
      ->getStorage('paragraphs_type')
      ->load($id);

    if (!$paragraphType) {
      return [
        'success' => FALSE,
        'error' => "Paragraph type '$id' not found.",
      ];
    }

    $fields = $this->getFieldsForBundle($id);

    return [
      'success' => TRUE,
      'data' => [
        'id' => $paragraphType->id(),
        'label' => $paragraphType->label(),
        'description' => $paragraphType->getDescription() ?? '',
        'fields' => $fields,
        'admin_path' => "/admin/structure/paragraphs_type/$id",
      ],
    ];
  }

  /**
   * Create a new paragraph type.
   *
   * @param string $id
   *   Machine name (lowercase, underscores).
   * @param string $label
   *   Human-readable name.
   * @param string $description
   *   Optional description.
   *
   * @return array
   *   Result with success status and data or error.
   */
  public function createParagraphType(string $id, string $label, string $description = ''): array {
    if (!$this->accessManager->canWrite('structure')) {
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

    // Check if paragraph type already exists.
    $existing = $this->entityTypeManager
      ->getStorage('paragraphs_type')
      ->load($id);

    if ($existing) {
      return [
        'success' => FALSE,
        'error' => "Paragraph type '$id' already exists.",
      ];
    }

    try {
      $paragraphType = ParagraphsType::create([
        'id' => $id,
        'label' => $label,
        'description' => $description,
      ]);

      $paragraphType->save();

      $this->auditLogger->logSuccess('create_paragraph_type', 'paragraphs_type', $id, [
        'label' => $label,
        'description' => $description,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'label' => $label,
          'message' => "Paragraph type '$label' ($id) created successfully.",
          'admin_path' => "/admin/structure/paragraphs_type/$id",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_paragraph_type', 'paragraphs_type', $id, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to create paragraph type: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Delete a paragraph type.
   *
   * @param string $id
   *   Paragraph type machine name.
   * @param bool $force
   *   If TRUE, delete even if paragraphs exist (dangerous!).
   *
   * @return array
   *   Result with success status.
   */
  public function deleteParagraphType(string $id, bool $force = FALSE): array {
    if (!$this->accessManager->canWrite('structure')) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $paragraphType = $this->entityTypeManager
      ->getStorage('paragraphs_type')
      ->load($id);

    if (!$paragraphType) {
      return [
        'success' => FALSE,
        'error' => "Paragraph type '$id' not found.",
      ];
    }

    // Check for existing paragraphs using this type.
    // SECURITY NOTE: accessCheck(FALSE) is intentional here.
    // This is a system-level count query to prevent accidental data loss.
    // We need to count ALL paragraphs of this type to warn about deletions.
    $usageCount = $this->entityTypeManager
      ->getStorage('paragraph')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $id)
      ->count()
      ->execute();

    if ($usageCount > 0 && !$force) {
      return [
        'success' => FALSE,
        'error' => "Paragraph type '$id' is in use by $usageCount paragraphs. Delete paragraphs first or use force=true (dangerous!).",
        'usage_count' => (int) $usageCount,
      ];
    }

    try {
      $label = $paragraphType->label();
      $paragraphType->delete();

      $this->auditLogger->logSuccess('delete_paragraph_type', 'paragraphs_type', $id, [
        'label' => $label,
        'force' => $force,
        'deleted_paragraphs' => $force ? (int) $usageCount : 0,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'message' => "Paragraph type '$label' ($id) deleted successfully.",
          'deleted_paragraphs' => $force ? (int) $usageCount : 0,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_paragraph_type', 'paragraphs_type', $id, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to delete paragraph type: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Add a field to a paragraph type.
   *
   * @param string $bundle
   *   Paragraph type machine name.
   * @param string $fieldName
   *   Field machine name (will be prefixed with 'field_' if needed).
   * @param string $fieldType
   *   Field type (use aliases from getFieldTypes).
   * @param array $settings
   *   Additional settings: label, required, description, cardinality,
   *   target_type (for entity_reference), allowed_values (for lists).
   *
   * @return array
   *   Result with success status.
   */
  public function addField(string $bundle, string $fieldName, string $fieldType, array $settings = []): array {
    if (!$this->accessManager->canWrite('structure')) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Normalize field name.
    if (!str_starts_with($fieldName, 'field_')) {
      $fieldName = 'field_' . $fieldName;
    }

    // Validate field name.
    if (!preg_match('/^field_[a-z][a-z0-9_]*$/', $fieldName)) {
      return [
        'success' => FALSE,
        'error' => 'Invalid field name. Use lowercase letters, numbers, and underscores after "field_".',
      ];
    }

    if (strlen($fieldName) > 32) {
      return [
        'success' => FALSE,
        'error' => 'Field name must be 32 characters or less.',
      ];
    }

    // Check field type.
    $available = FieldInput::availableTypes($this->fieldTypeManager, $this->entityTypeManager);
    if (!in_array($fieldType, $available, TRUE)) {
      return [
        'success' => FALSE,
        'error' => isset(FieldInput::TYPES[$fieldType])
          ? "Field type '$fieldType' is not available on this site: the module that provides it is not enabled."
          : "Unknown field type '$fieldType'.",
        'available_types' => $available,
      ];
    }

    // Check if paragraph type exists.
    $paragraphType = $this->entityTypeManager
      ->getStorage('paragraphs_type')
      ->load($bundle);

    if (!$paragraphType) {
      return [
        'success' => FALSE,
        'error' => "Paragraph type '$bundle' does not exist.",
      ];
    }

    // Check if field already exists on this bundle.
    $bundleInfo = $this->entityFieldManager->getFieldDefinitions('paragraph', $bundle);
    if (isset($bundleInfo[$fieldName])) {
      return [
        'success' => FALSE,
        'error' => "Field '$fieldName' already exists on paragraph.$bundle.",
      ];
    }

    $typeConfig = FieldInput::TYPES[$fieldType];
    $label = $settings['label'] ?? ucfirst(str_replace(['field_', '_'], ['', ' '], $fieldName));

    // The list inputs, read before anything is made.
    $allowedValues = NULL;
    $targetBundles = [];
    $targetType = FieldInput::referenceTarget($typeConfig, $settings['target_type'] ?? NULL);
    try {
      if (in_array($typeConfig['type'], ['list_string', 'list_integer'], TRUE) && isset($settings['allowed_values'])) {
        $allowedValues = $this->formatAllowedValues($settings['allowed_values']);
      }
      if ($targetType !== NULL) {
        $targetBundles = FieldInput::targetBundles($settings['target_bundles'] ?? NULL, $targetType, $this->entityTypeManager);
      }
    }
    catch (\InvalidArgumentException $e) {
      return ['success' => FALSE, 'error' => $e->getMessage()];
    }

    try {
      // Check if field storage exists (might be used on other bundles).
      $fieldStorageId = "paragraph.$fieldName";
      $fieldStorage = $this->entityTypeManager
        ->getStorage('field_storage_config')
        ->load($fieldStorageId);

      if (!$fieldStorage) {
        // Create field storage.
        $storageSettings = array_merge(
          $typeConfig['storage_settings'],
          $settings['storage_settings'] ?? []
        );

        // Handle entity reference target type.
        if ($targetType !== NULL) {
          $storageSettings['target_type'] = $targetType;
        }

        // Handle list allowed values.
        if ($allowedValues !== NULL) {
          $storageSettings['allowed_values'] = $allowedValues;
        }

        $fieldStorage = FieldStorageConfig::create([
          'field_name' => $fieldName,
          'entity_type' => 'paragraph',
          'type' => $typeConfig['type'],
          'cardinality' => $settings['cardinality'] ?? $typeConfig['cardinality'] ?? 1,
          'settings' => $storageSettings,
        ]);
        $fieldStorage->save();
      }

      // Create field instance.
      $fieldSettings = $settings['field_settings'] ?? [];

      // Handle entity reference handler settings.
      if ($targetType !== NULL) {
        $fieldSettings['handler'] = 'default:' . $targetType;
        if ($targetBundles !== []) {
          $fieldSettings['handler_settings']['target_bundles'] = $targetBundles;
        }
      }

      $field = FieldConfig::create([
        'field_storage' => $fieldStorage,
        'bundle' => $bundle,
        'label' => $label,
        'required' => $settings['required'] ?? FALSE,
        'description' => $settings['description'] ?? '',
        'default_value' => $settings['default_value'] ?? [],
        'settings' => $fieldSettings,
      ]);
      $field->save();

      // The default displays, made when there are none.
      $this->displays->getFormDisplay('paragraph', $bundle)
        ->setComponent($fieldName, ['type' => $typeConfig['widget'], 'settings' => $settings['widget_settings'] ?? []])
        ->save();
      $this->displays->getViewDisplay('paragraph', $bundle)
        ->setComponent($fieldName, [
          'type' => $typeConfig['formatter'],
          'settings' => $settings['formatter_settings'] ?? [],
          'label' => 'above',
        ])
        ->save();

      $this->auditLogger->logSuccess('add_paragraph_field', 'field_config', "paragraph.$bundle.$fieldName", [
        'field_type' => $fieldType,
        'label' => $label,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'field_name' => $fieldName,
          'bundle' => $bundle,
          'field_type' => $fieldType,
          'label' => $label,
          'message' => "Field '$label' ($fieldName) added to paragraph.$bundle.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('add_paragraph_field', 'field_config', "paragraph.$bundle.$fieldName", [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to create field: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Delete a field from a paragraph type.
   *
   * @param string $bundle
   *   Paragraph type machine name.
   * @param string $fieldName
   *   Field machine name.
   *
   * @return array
   *   Result with success status.
   */
  public function deleteField(string $bundle, string $fieldName): array {
    if (!$this->accessManager->canWrite('structure')) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Normalize field name.
    if (!str_starts_with($fieldName, 'field_')) {
      $fieldName = 'field_' . $fieldName;
    }

    $fieldConfigId = "paragraph.$bundle.$fieldName";
    $field = $this->entityTypeManager
      ->getStorage('field_config')
      ->load($fieldConfigId);

    if (!$field) {
      return [
        'success' => FALSE,
        'error' => "Field '$fieldName' not found on paragraph.$bundle. Use mcp_paragraphs_get_type to see available fields.",
      ];
    }

    try {
      $label = $field->label();
      $field->delete();

      $this->auditLogger->logSuccess('delete_paragraph_field', 'field_config', $fieldConfigId, [
        'label' => $label,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'field_name' => $fieldName,
          'bundle' => $bundle,
          'message' => "Field '$label' ($fieldName) deleted from paragraph.$bundle.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_paragraph_field', 'field_config', $fieldConfigId, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to delete field: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Get fields for a paragraph bundle.
   *
   * @param string $bundle
   *   The paragraph type machine name.
   *
   * @return array
   *   Array of field information.
   */
  protected function getFieldsForBundle(string $bundle): array {
    $fields = [];
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('paragraph', $bundle);

    foreach ($fieldDefinitions as $fieldName => $definition) {
      // Skip base fields.
      if (!str_starts_with($fieldName, 'field_')) {
        continue;
      }

      $fields[] = [
        'name' => $fieldName,
        'type' => $definition->getType(),
        'label' => $definition->getLabel(),
        'required' => $definition->isRequired(),
        'description' => $definition->getDescription() ?? '',
        'cardinality' => $definition->getFieldStorageDefinition()->getCardinality(),
      ];
    }

    return $fields;
  }

  /**
   * Format allowed values for list fields.
   *
   * @param array $values
   *   Either ['key' => 'label'] or ['label1', 'label2'].
   *
   * @return array
   *   Formatted allowed values.
   */
  protected function formatAllowedValues(mixed $values): array {
    return FieldInput::allowedValues($values);
  }

}
