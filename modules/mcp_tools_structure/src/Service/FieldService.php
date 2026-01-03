<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for managing fields.
 */
class FieldService {

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
    'timestamp' => [
      'type' => 'timestamp',
      'widget' => 'datetime_timestamp',
      'formatter' => 'timestamp',
      'storage_settings' => [],
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
   * Get available field types.
   *
   * @return array
   *   List of field types with labels.
   */
  public function getFieldTypes(): array {
    $types = [];

    foreach (self::FIELD_TYPE_DEFAULTS as $alias => $config) {
      $definition = $this->fieldTypeManager->getDefinition($config['type'], FALSE);
      $types[] = [
        'id' => $alias,
        'drupal_type' => $config['type'],
        'label' => $definition ? (string) $definition['label'] : $alias,
        'description' => $definition ? (string) ($definition['description'] ?? '') : '',
      ];
    }

    return [
      'types' => $types,
      'total' => count($types),
    ];
  }

  /**
   * Add a field to an entity bundle.
   *
   * @param string $entityType
   *   Entity type ID (e.g., 'node', 'taxonomy_term', 'user').
   * @param string $bundle
   *   Bundle ID (e.g., 'article', 'tags').
   * @param string $fieldName
   *   Field machine name (will be prefixed with 'field_' if needed).
   * @param string $fieldType
   *   Field type (use aliases from getFieldTypes).
   * @param string $label
   *   Human-readable label.
   * @param array $options
   *   Additional options: required, description, cardinality, default_value,
   *   settings, target_type (for entity_reference), allowed_values (for lists).
   *
   * @return array
   *   Result with success status.
   */
  public function addField(
    string $entityType,
    string $bundle,
    string $fieldName,
    string $fieldType,
    string $label,
    array $options = []
  ): array {
    if (!$this->accessManager->canWrite()) {
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
        'error' => "Unknown field type '$fieldType'. Use getFieldTypes() to see available types.",
        'available_types' => array_keys(self::FIELD_TYPE_DEFAULTS),
      ];
    }

    $typeConfig = self::FIELD_TYPE_DEFAULTS[$fieldType];

    // Check if bundle exists.
    $bundleInfo = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);
    if (empty($bundleInfo)) {
      return [
        'success' => FALSE,
        'error' => "Bundle '$bundle' does not exist for entity type '$entityType'.",
      ];
    }

    // Check if field already exists on this bundle.
    if (isset($bundleInfo[$fieldName])) {
      return [
        'success' => FALSE,
        'error' => "Field '$fieldName' already exists on $entityType.$bundle.",
      ];
    }

    try {
      // Check if field storage exists (might be used on other bundles).
      $fieldStorageId = "$entityType.$fieldName";
      $fieldStorage = $this->entityTypeManager
        ->getStorage('field_storage_config')
        ->load($fieldStorageId);

      if (!$fieldStorage) {
        // Create field storage.
        $storageSettings = array_merge(
          $typeConfig['storage_settings'],
          $options['storage_settings'] ?? []
        );

        // Handle entity reference target type.
        if ($typeConfig['type'] === 'entity_reference') {
          $targetType = $options['target_type'] ?? 'node';
          $storageSettings['target_type'] = $targetType;
        }

        // Handle list allowed values.
        if (in_array($typeConfig['type'], ['list_string', 'list_integer']) && isset($options['allowed_values'])) {
          $storageSettings['allowed_values'] = $this->formatAllowedValues($options['allowed_values']);
        }

        $fieldStorage = FieldStorageConfig::create([
          'field_name' => $fieldName,
          'entity_type' => $entityType,
          'type' => $typeConfig['type'],
          'cardinality' => $options['cardinality'] ?? 1,
          'settings' => $storageSettings,
        ]);
        $fieldStorage->save();
      }

      // Create field instance.
      $fieldSettings = $options['settings'] ?? [];

      // Handle entity reference handler settings.
      if ($typeConfig['type'] === 'entity_reference') {
        $targetType = $options['target_type'] ?? 'node';
        $targetBundles = $options['target_bundles'] ?? [];

        $fieldSettings['handler'] = 'default:' . $targetType;
        if (!empty($targetBundles)) {
          $fieldSettings['handler_settings']['target_bundles'] = array_combine($targetBundles, $targetBundles);
        }
      }

      $field = FieldConfig::create([
        'field_storage' => $fieldStorage,
        'bundle' => $bundle,
        'label' => $label,
        'required' => $options['required'] ?? FALSE,
        'description' => $options['description'] ?? '',
        'default_value' => $options['default_value'] ?? [],
        'settings' => $fieldSettings,
      ]);
      $field->save();

      // Configure form display.
      $this->configureFormDisplay(
        $entityType,
        $bundle,
        $fieldName,
        $typeConfig['widget'],
        $options['widget_settings'] ?? []
      );

      // Configure view display.
      $this->configureViewDisplay(
        $entityType,
        $bundle,
        $fieldName,
        $typeConfig['formatter'],
        $options['formatter_settings'] ?? []
      );

      $this->auditLogger->logSuccess('add_field', 'field_config', "$entityType.$bundle.$fieldName", [
        'field_type' => $fieldType,
        'label' => $label,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'field_name' => $fieldName,
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'field_type' => $fieldType,
          'label' => $label,
          'message' => "Field '$label' ($fieldName) added to $entityType.$bundle.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('add_field', 'field_config', "$entityType.$bundle.$fieldName", [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to create field: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Delete a field from a bundle.
   *
   * @param string $entityType
   *   Entity type ID.
   * @param string $bundle
   *   Bundle ID.
   * @param string $fieldName
   *   Field machine name.
   *
   * @return array
   *   Result with success status.
   */
  public function deleteField(string $entityType, string $bundle, string $fieldName): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Normalize field name.
    if (!str_starts_with($fieldName, 'field_')) {
      $fieldName = 'field_' . $fieldName;
    }

    $fieldConfigId = "$entityType.$bundle.$fieldName";
    $field = $this->entityTypeManager
      ->getStorage('field_config')
      ->load($fieldConfigId);

    if (!$field) {
      return [
        'success' => FALSE,
        'error' => "Field '$fieldName' not found on $entityType.$bundle.",
      ];
    }

    try {
      $label = $field->label();
      $field->delete();

      $this->auditLogger->logSuccess('delete_field', 'field_config', $fieldConfigId, [
        'label' => $label,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'field_name' => $fieldName,
          'message' => "Field '$label' ($fieldName) deleted from $entityType.$bundle.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_field', 'field_config', $fieldConfigId, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to delete field: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Configure form display for a field.
   */
  protected function configureFormDisplay(
    string $entityType,
    string $bundle,
    string $fieldName,
    string $widget,
    array $settings = []
  ): void {
    $formDisplay = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load("$entityType.$bundle.default");

    if (!$formDisplay) {
      $formDisplay = $this->entityTypeManager
        ->getStorage('entity_form_display')
        ->create([
          'targetEntityType' => $entityType,
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
    string $entityType,
    string $bundle,
    string $fieldName,
    string $formatter,
    array $settings = []
  ): void {
    $viewDisplay = $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->load("$entityType.$bundle.default");

    if (!$viewDisplay) {
      $viewDisplay = $this->entityTypeManager
        ->getStorage('entity_view_display')
        ->create([
          'targetEntityType' => $entityType,
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
