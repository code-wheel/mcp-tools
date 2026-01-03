<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_paragraphs\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Service for managing Paragraphs types and fields.
 */
class ParagraphsService {

  /**
   * Common field type mappings with default settings.
   */
  protected const FIELD_TYPE_DEFAULTS = [
    'string' => [
      'type' => 'string',
      'widget' => 'string_textfield',
      'formatter' => 'string',
      'storage_settings' => ['max_length' => 255],
    ],
    'string_long' => [
      'type' => 'string_long',
      'widget' => 'string_textarea',
      'formatter' => 'basic_string',
      'storage_settings' => [],
    ],
    'text' => [
      'type' => 'text',
      'widget' => 'text_textfield',
      'formatter' => 'text_default',
      'storage_settings' => ['max_length' => 255],
    ],
    'text_long' => [
      'type' => 'text_long',
      'widget' => 'text_textarea',
      'formatter' => 'text_default',
      'storage_settings' => [],
    ],
    'text_with_summary' => [
      'type' => 'text_with_summary',
      'widget' => 'text_textarea_with_summary',
      'formatter' => 'text_default',
      'storage_settings' => [],
    ],
    'integer' => [
      'type' => 'integer',
      'widget' => 'number',
      'formatter' => 'number_integer',
      'storage_settings' => [],
    ],
    'decimal' => [
      'type' => 'decimal',
      'widget' => 'number',
      'formatter' => 'number_decimal',
      'storage_settings' => ['precision' => 10, 'scale' => 2],
    ],
    'float' => [
      'type' => 'float',
      'widget' => 'number',
      'formatter' => 'number_decimal',
      'storage_settings' => [],
    ],
    'boolean' => [
      'type' => 'boolean',
      'widget' => 'boolean_checkbox',
      'formatter' => 'boolean',
      'storage_settings' => [],
    ],
    'email' => [
      'type' => 'email',
      'widget' => 'email_default',
      'formatter' => 'basic_string',
      'storage_settings' => [],
    ],
    'link' => [
      'type' => 'link',
      'widget' => 'link_default',
      'formatter' => 'link',
      'storage_settings' => [],
    ],
    'datetime' => [
      'type' => 'datetime',
      'widget' => 'datetime_default',
      'formatter' => 'datetime_default',
      'storage_settings' => ['datetime_type' => 'datetime'],
    ],
    'date' => [
      'type' => 'datetime',
      'widget' => 'datetime_default',
      'formatter' => 'datetime_default',
      'storage_settings' => ['datetime_type' => 'date'],
    ],
    'image' => [
      'type' => 'image',
      'widget' => 'image_image',
      'formatter' => 'image',
      'storage_settings' => ['target_type' => 'file'],
    ],
    'file' => [
      'type' => 'file',
      'widget' => 'file_generic',
      'formatter' => 'file_default',
      'storage_settings' => ['target_type' => 'file'],
    ],
    'entity_reference' => [
      'type' => 'entity_reference',
      'widget' => 'entity_reference_autocomplete',
      'formatter' => 'entity_reference_label',
      'storage_settings' => [],
    ],
    'list_string' => [
      'type' => 'list_string',
      'widget' => 'options_select',
      'formatter' => 'list_default',
      'storage_settings' => ['allowed_values' => []],
    ],
    'list_integer' => [
      'type' => 'list_integer',
      'widget' => 'options_select',
      'formatter' => 'list_default',
      'storage_settings' => ['allowed_values' => []],
    ],
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected FieldTypePluginManagerInterface $fieldTypeManager,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
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
        'icon_uuid' => $type->getIconUuid(),
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
        'icon_uuid' => $paragraphType->getIconUuid(),
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
    if (!isset(self::FIELD_TYPE_DEFAULTS[$fieldType])) {
      return [
        'success' => FALSE,
        'error' => "Unknown field type '$fieldType'.",
        'available_types' => array_keys(self::FIELD_TYPE_DEFAULTS),
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

    $typeConfig = self::FIELD_TYPE_DEFAULTS[$fieldType];
    $label = $settings['label'] ?? ucfirst(str_replace(['field_', '_'], ['', ' '], $fieldName));

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
        if ($typeConfig['type'] === 'entity_reference') {
          $targetType = $settings['target_type'] ?? 'node';
          $storageSettings['target_type'] = $targetType;
        }

        // Handle list allowed values.
        if (in_array($typeConfig['type'], ['list_string', 'list_integer']) && isset($settings['allowed_values'])) {
          $storageSettings['allowed_values'] = $this->formatAllowedValues($settings['allowed_values']);
        }

        $fieldStorage = FieldStorageConfig::create([
          'field_name' => $fieldName,
          'entity_type' => 'paragraph',
          'type' => $typeConfig['type'],
          'cardinality' => $settings['cardinality'] ?? 1,
          'settings' => $storageSettings,
        ]);
        $fieldStorage->save();
      }

      // Create field instance.
      $fieldSettings = $settings['field_settings'] ?? [];

      // Handle entity reference handler settings.
      if ($typeConfig['type'] === 'entity_reference') {
        $targetType = $settings['target_type'] ?? 'node';
        $targetBundles = $settings['target_bundles'] ?? [];

        $fieldSettings['handler'] = 'default:' . $targetType;
        if (!empty($targetBundles)) {
          $fieldSettings['handler_settings']['target_bundles'] = array_combine($targetBundles, $targetBundles);
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

      // Configure form display.
      $this->configureFormDisplay($bundle, $fieldName, $typeConfig['widget'], $settings['widget_settings'] ?? []);

      // Configure view display.
      $this->configureViewDisplay($bundle, $fieldName, $typeConfig['formatter'], $settings['formatter_settings'] ?? []);

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
        'error' => "Field '$fieldName' not found on paragraph.$bundle.",
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
   * Configure form display for a field.
   */
  protected function configureFormDisplay(
    string $bundle,
    string $fieldName,
    string $widget,
    array $settings = []
  ): void {
    $formDisplay = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load("paragraph.$bundle.default");

    if (!$formDisplay) {
      $formDisplay = $this->entityTypeManager
        ->getStorage('entity_form_display')
        ->create([
          'targetEntityType' => 'paragraph',
          'bundle' => $bundle,
          'mode' => 'default',
          'status' => TRUE,
        ]);
    }

    $formDisplay->setComponent($fieldName, [
      'type' => $widget,
      'settings' => $settings,
    ])->save();
  }

  /**
   * Configure view display for a field.
   */
  protected function configureViewDisplay(
    string $bundle,
    string $fieldName,
    string $formatter,
    array $settings = []
  ): void {
    $viewDisplay = $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->load("paragraph.$bundle.default");

    if (!$viewDisplay) {
      $viewDisplay = $this->entityTypeManager
        ->getStorage('entity_view_display')
        ->create([
          'targetEntityType' => 'paragraph',
          'bundle' => $bundle,
          'mode' => 'default',
          'status' => TRUE,
        ]);
    }

    $viewDisplay->setComponent($fieldName, [
      'type' => $formatter,
      'settings' => $settings,
      'label' => 'above',
    ])->save();
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
  protected function formatAllowedValues(array $values): array {
    // Check if associative array.
    if (array_keys($values) !== range(0, count($values) - 1)) {
      // Already key => label format.
      return $values;
    }

    // Convert to key => label where key is sanitized label.
    $result = [];
    foreach ($values as $value) {
      $key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $value));
      $result[$key] = $value;
    }
    return $result;
  }

}
