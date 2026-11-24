<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Service;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools\Service\FieldInput;

/**
 * Service for managing fields.
 */
class FieldService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected FieldTypePluginManagerInterface $fieldTypeManager,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
    protected EntityDisplayRepositoryInterface $displays,
  ) {}

  /**
   * Get available field types.
   *
   * @return array
   *   List of field types with labels.
   */
  public function getFieldTypes(): array {
    $types = [];

    // A type this site cannot add is not offered.
    foreach (FieldInput::availableTypes($this->fieldTypeManager, $this->entityTypeManager) as $alias) {
      $config = FieldInput::TYPES[$alias];
      $definition = $this->fieldTypeManager->getDefinition($config['type'], FALSE);
      if (!$definition) {
        continue;
      }
      // Core describes some field types with a list of lines.
      $description = $definition['description'] ?? '';
      if (is_array($description)) {
        $description = implode(' ', array_map('strval', $description));
      }
      $types[] = [
        'id' => $alias,
        'drupal_type' => $config['type'],
        'label' => (string) $definition['label'],
        'description' => (string) $description,
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
    array $options = [],
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

    // Options on a type that has none were dropped without a word, and the
    // call said success. A text or a whole number with options can only be
    // meant as a list, so that is what is made. Any other type is refused.
    $typeAsked = $fieldType;
    $isList = $fieldType === 'list_string' || $fieldType === 'list_integer';
    if (!empty($options['allowed_values']) && isset(FieldInput::TYPES[$fieldType]) && !$isList) {
      if ($fieldType !== 'string' && $fieldType !== 'integer') {
        $error = "allowed_values is for list fields, and '$fieldType' is not one. Use field_type list_string for"
          . ' options that are text or list_integer for whole numbers, or leave allowed_values out.';
        return ['success' => FALSE, 'error' => $error];
      }
      $fieldType = $fieldType === 'integer' ? 'list_integer' : 'list_string';
    }

    // Drupal 12 has no text_with_summary in core: the type moved to the
    // module of that name. Where the site does not have it, a long
    // formatted text is the nearest type, and the answer says so.
    $noSummaryType = $fieldType === 'text_with_summary' && !$this->fieldTypeManager->hasDefinition('text_with_summary');
    if ($noSummaryType) {
      $fieldType = 'text_long';
    }

    // Check field type.
    if (!isset(FieldInput::TYPES[$fieldType])) {
      return [
        'success' => FALSE,
        'error' => "Unknown field type '$fieldType'. Use mcp_structure_list_field_types to see available types.",
        'available_types' => array_column($this->getFieldTypes()['types'], 'id'),
      ];
    }

    if (!in_array($fieldType, FieldInput::availableTypes($this->fieldTypeManager, $this->entityTypeManager), TRUE)) {
      return [
        'success' => FALSE,
        'error' => "Field type '$fieldType' is not available on this site: the module that provides it is not enabled. Use mcp_structure_list_field_types to see available types.",
        'available_types' => array_column($this->getFieldTypes()['types'], 'id'),
      ];
    }

    $typeConfig = FieldInput::TYPES[$fieldType];

    // The options of a list, read before anything is made.
    $allowedValues = NULL;
    if (in_array($typeConfig['type'], ['list_string', 'list_integer'], TRUE) && isset($options['allowed_values'])) {
      try {
        $allowedValues = FieldInput::allowedValues($options['allowed_values']);
      }
      catch (\InvalidArgumentException $e) {
        return ['success' => FALSE, 'error' => $e->getMessage()];
      }
    }

    // The bundles a reference may point to, read before anything is made.
    $targetBundles = [];
    $targetType = FieldInput::referenceTarget($typeConfig, $options['target_type'] ?? NULL);
    if ($targetType !== NULL) {
      try {
        $targetBundles = FieldInput::targetBundles($options['target_bundles'] ?? NULL, $targetType, $this->entityTypeManager);
      }
      catch (\InvalidArgumentException $e) {
        return ['success' => FALSE, 'error' => $e->getMessage()];
      }
    }

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
        if ($targetType !== NULL) {
          $storageSettings['target_type'] = $targetType;
        }

        // Handle list allowed values.
        if ($allowedValues !== NULL) {
          $storageSettings['allowed_values'] = $allowedValues;
        }

        $fieldStorage = $this->entityTypeManager->getStorage('field_storage_config')->create([
          'field_name' => $fieldName,
          'entity_type' => $entityType,
          'type' => $typeConfig['type'],
          'cardinality' => $options['cardinality'] ?? $typeConfig['cardinality'] ?? 1,
          'settings' => $storageSettings,
        ]);
        $fieldStorage->save();
      }

      // Create field instance.
      $fieldSettings = $options['settings'] ?? [];

      // Handle entity reference handler settings.
      if ($targetType !== NULL) {
        $fieldSettings['handler'] = 'default:' . $targetType;
        if ($targetBundles !== []) {
          $fieldSettings['handler_settings']['target_bundles'] = $targetBundles;
        }
      }

      $field = $this->entityTypeManager->getStorage('field_config')->create([
        'field_storage' => $fieldStorage,
        'bundle' => $bundle,
        'label' => $label,
        'required' => $options['required'] ?? FALSE,
        'description' => $options['description'] ?? '',
        'default_value' => $options['default_value'] ?? [],
        'settings' => $fieldSettings,
      ]);
      $field->save();

      // The default displays, made when there are none.
      $this->displays->getFormDisplay($entityType, $bundle)
        ->setComponent($fieldName, ['type' => $typeConfig['widget'], 'settings' => $options['widget_settings'] ?? []])
        ->save();
      $this->displays->getViewDisplay($entityType, $bundle)
        ->setComponent($fieldName, [
          'type' => $typeConfig['formatter'],
          'settings' => $options['formatter_settings'] ?? [],
          'label' => 'above',
        ])
        ->save();

      $this->auditLogger->logSuccess('add_field', 'field_config', "$entityType.$bundle.$fieldName", [
        'field_type' => $fieldType,
        'label' => $label,
      ]);

      $message = "Field '$label' ($fieldName) added to $entityType.$bundle.";
      if ($fieldType !== $typeAsked) {
        $message .= " It is a $fieldType field, not $typeAsked, because "
          . ($noSummaryType ? 'this site has no text_with_summary type (Drupal 12 moved it to a module of that name).' : 'allowed_values were given.');
      }

      return [
        'success' => TRUE,
        'data' => [
          'field_name' => $fieldName,
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'field_type' => $fieldType,
          'message' => $message,
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
        'error' => "Field '$fieldName' not found on $entityType.$bundle. Use mcp_structure_get_content_type to see available fields.",
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

}
