<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_config\Service;

use Drupal\Core\Config\StorageInterface;

/**
 * Service for previewing configuration operations before execution.
 *
 * Provides dry-run capabilities for various configuration management operations.
 */
class OperationPreviewService {

  public function __construct(
    protected StorageInterface $activeStorage,
    protected ConfigComparisonService $configComparisonService,
  ) {}

  /**
   * Preview what an operation would do without executing it.
   *
   * Dry-run mode: shows what an operation WOULD do without doing it.
   *
   * @param string $operation
   *   The operation type (e.g., 'export_config', 'create_content_type').
   * @param array $params
   *   Parameters for the operation.
   *
   * @return array
   *   Result array describing what would happen.
   */
  public function previewOperation(string $operation, array $params = []): array {
    $preview = match ($operation) {
      'export_config' => $this->previewExportConfig(),
      'import_config' => $this->previewImportConfig(),
      'delete_config' => $this->previewDeleteConfig($params['config_name'] ?? ''),
      'create_role' => $this->previewCreateRole($params),
      'delete_role' => $this->previewDeleteRole($params),
      'grant_permissions' => $this->previewGrantPermissions($params),
      'revoke_permissions' => $this->previewRevokePermissions($params),
      'create_content_type' => $this->previewCreateContentType($params),
      'delete_content_type' => $this->previewDeleteContentType($params),
      'add_field' => $this->previewAddField($params),
      'delete_field' => $this->previewDeleteField($params),
      'create_vocabulary' => $this->previewCreateVocabulary($params),
      'create_view' => $this->previewCreateView($params),
      default => [
        'success' => FALSE,
        'error' => sprintf(
          "Unknown operation '%s'. Supported: export_config, import_config, delete_config, create_role, delete_role, grant_permissions, revoke_permissions, create_content_type, delete_content_type, add_field, delete_field, create_vocabulary, create_view",
          $operation
        ),
      ],
    };

    if ($preview['success'] ?? FALSE) {
      $preview['data']['dry_run'] = TRUE;
      $preview['data']['operation'] = $operation;
      $preview['data']['note'] = 'This is a preview. No changes have been made.';
    }

    return $preview;
  }

  /**
   * Preview export config operation.
   */
  public function previewExportConfig(): array {
    $changes = $this->configComparisonService->getConfigChanges();

    if (!$changes['success']) {
      return $changes;
    }

    $data = $changes['data'];

    return [
      'success' => TRUE,
      'data' => [
        'action' => 'Export active configuration to sync directory',
        'will_create' => count($data['changes']['create'] ?? []),
        'will_update' => count($data['changes']['update'] ?? []),
        'will_delete' => count($data['changes']['delete'] ?? []),
        'total_changes' => $data['total_changes'],
        'affected_configs' => $data['changes'],
        'description' => $data['has_changes']
          ? 'This will write all active configuration to the sync directory, overwriting existing files.'
          : 'No changes to export. Active configuration matches sync directory.',
      ],
    ];
  }

  /**
   * Preview import config operation.
   */
  public function previewImportConfig(): array {
    return $this->configComparisonService->previewImportConfig();
  }

  /**
   * Preview delete config operation.
   */
  public function previewDeleteConfig(string $configName): array {
    if (empty($configName)) {
      return [
        'success' => FALSE,
        'error' => 'config_name parameter is required for delete_config preview.',
      ];
    }

    $exists = $this->activeStorage->read($configName) !== FALSE;

    if (!$exists) {
      return [
        'success' => TRUE,
        'data' => [
          'action' => 'Delete configuration',
          'config_name' => $configName,
          'description' => "Configuration '$configName' does not exist. No action would be taken.",
        ],
      ];
    }

    // Check for dependencies.
    $dependents = $this->findDependents($configName);

    return [
      'success' => TRUE,
      'data' => [
        'action' => 'Delete configuration',
        'config_name' => $configName,
        'exists' => TRUE,
        'dependents' => $dependents,
        'description' => empty($dependents)
          ? "Configuration '$configName' would be deleted."
          : sprintf(
            "Configuration '%s' would be deleted. WARNING: %d dependent configs may be affected.",
            $configName,
            count($dependents)
          ),
      ],
    ];
  }

  /**
   * Preview create role operation.
   */
  public function previewCreateRole(array $params): array {
    $roleId = $params['id'] ?? $params['role_id'] ?? '';
    $label = $params['label'] ?? '';

    if ($roleId === '') {
      return [
        'success' => FALSE,
        'error' => 'id parameter is required for create_role preview.',
      ];
    }

    $configName = "user.role.$roleId";
    $exists = $this->activeStorage->read($configName) !== FALSE;

    return [
      'success' => TRUE,
      'data' => [
        'action' => 'Create role',
        'role_id' => $roleId,
        'label' => $label ?: $roleId,
        'already_exists' => $exists,
        'configs_created' => [$configName],
        'description' => $exists
          ? "Role '$roleId' already exists. Operation would fail or update existing."
          : "Would create new role '$roleId'.",
      ],
    ];
  }

  /**
   * Preview delete role operation.
   */
  public function previewDeleteRole(array $params): array {
    $roleId = $params['id'] ?? $params['role_id'] ?? '';

    if ($roleId === '') {
      return [
        'success' => FALSE,
        'error' => 'id parameter is required for delete_role preview.',
      ];
    }

    $configName = "user.role.$roleId";
    $exists = $this->activeStorage->read($configName) !== FALSE;
    if (!$exists) {
      return [
        'success' => TRUE,
        'data' => [
          'action' => 'Delete role',
          'role_id' => $roleId,
          'description' => "Role '$roleId' does not exist. No action would be taken.",
        ],
      ];
    }

    return [
      'success' => TRUE,
      'data' => [
        'action' => 'Delete role',
        'role_id' => $roleId,
        'configs_deleted' => [$configName],
        'description' => "Would delete role '$roleId'.",
      ],
    ];
  }

  /**
   * Preview granting permissions to a role.
   */
  public function previewGrantPermissions(array $params): array {
    $roleId = $params['role'] ?? $params['id'] ?? $params['role_id'] ?? '';
    $permissions = $params['permissions'] ?? [];

    if ($roleId === '') {
      return [
        'success' => FALSE,
        'error' => 'role parameter is required for grant_permissions preview.',
      ];
    }

    if (is_string($permissions)) {
      $permissions = array_filter(array_map('trim', explode(',', $permissions)));
    }
    if (!is_array($permissions)) {
      $permissions = [];
    }

    $configName = "user.role.$roleId";
    $roleData = $this->activeStorage->read($configName);
    if ($roleData === FALSE) {
      return [
        'success' => FALSE,
        'error' => "Role '$roleId' does not exist.",
      ];
    }

    $current = $roleData['permissions'] ?? [];
    if (!is_array($current)) {
      $current = [];
    }

    $normalized = array_values(array_unique(array_filter(array_map('strval', $permissions))));
    $willAdd = array_values(array_diff($normalized, $current));

    return [
      'success' => TRUE,
      'data' => [
        'action' => 'Grant permissions to role',
        'role_id' => $roleId,
        'config_updated' => $configName,
        'will_add' => $willAdd,
        'already_present' => array_values(array_intersect($normalized, $current)),
        'description' => empty($willAdd)
          ? 'All provided permissions are already granted.'
          : sprintf('Would grant %d permission(s) to role %s.', count($willAdd), $roleId),
      ],
    ];
  }

  /**
   * Preview revoking permissions from a role.
   */
  public function previewRevokePermissions(array $params): array {
    $roleId = $params['role'] ?? $params['id'] ?? $params['role_id'] ?? '';
    $permissions = $params['permissions'] ?? [];

    if ($roleId === '') {
      return [
        'success' => FALSE,
        'error' => 'role parameter is required for revoke_permissions preview.',
      ];
    }

    if (is_string($permissions)) {
      $permissions = array_filter(array_map('trim', explode(',', $permissions)));
    }
    if (!is_array($permissions)) {
      $permissions = [];
    }

    $configName = "user.role.$roleId";
    $roleData = $this->activeStorage->read($configName);
    if ($roleData === FALSE) {
      return [
        'success' => FALSE,
        'error' => "Role '$roleId' does not exist.",
      ];
    }

    $current = $roleData['permissions'] ?? [];
    if (!is_array($current)) {
      $current = [];
    }

    $normalized = array_values(array_unique(array_filter(array_map('strval', $permissions))));
    $willRemove = array_values(array_intersect($normalized, $current));

    return [
      'success' => TRUE,
      'data' => [
        'action' => 'Revoke permissions from role',
        'role_id' => $roleId,
        'config_updated' => $configName,
        'will_remove' => $willRemove,
        'not_present' => array_values(array_diff($normalized, $current)),
        'description' => empty($willRemove)
          ? 'None of the provided permissions are currently granted.'
          : sprintf('Would revoke %d permission(s) from role %s.', count($willRemove), $roleId),
      ],
    ];
  }

  /**
   * Preview create content type operation.
   */
  public function previewCreateContentType(array $params): array {
    $machineName = $params['machine_name'] ?? $params['id'] ?? '';
    $name = $params['name'] ?? $params['label'] ?? '';

    if (empty($machineName)) {
      return [
        'success' => FALSE,
        'error' => 'machine_name parameter is required for create_content_type preview.',
      ];
    }

    $configName = "node.type.$machineName";
    $exists = $this->activeStorage->read($configName) !== FALSE;

    return [
      'success' => TRUE,
      'data' => [
        'action' => 'Create content type',
        'machine_name' => $machineName,
        'name' => $name ?: $machineName,
        'already_exists' => $exists,
        'configs_created' => [
          $configName,
          "core.entity_form_display.node.$machineName.default",
          "core.entity_view_display.node.$machineName.default",
          "core.entity_view_display.node.$machineName.teaser",
        ],
        'description' => $exists
          ? "Content type '$machineName' already exists. Operation would fail or update existing."
          : "Would create new content type '$machineName' with default form and view displays.",
      ],
    ];
  }

  /**
   * Preview delete content type operation.
   */
  public function previewDeleteContentType(array $params): array {
    $machineName = $params['machine_name'] ?? $params['id'] ?? '';
    if ($machineName === '') {
      return [
        'success' => FALSE,
        'error' => 'id parameter is required for delete_content_type preview.',
      ];
    }

    $typeConfig = "node.type.$machineName";
    $exists = $this->activeStorage->read($typeConfig) !== FALSE;

    $related = [
      $typeConfig,
      "core.entity_form_display.node.$machineName.default",
      "core.entity_view_display.node.$machineName.default",
      "core.entity_view_display.node.$machineName.teaser",
    ];

    return [
      'success' => TRUE,
      'data' => [
        'action' => 'Delete content type',
        'machine_name' => $machineName,
        'exists' => $exists,
        'configs_deleted' => $related,
        'description' => $exists
          ? "Would attempt to delete content type '$machineName'. NOTE: deletion will fail if content exists unless the tool supports force deletion."
          : "Content type '$machineName' does not exist. No action would be taken.",
      ],
    ];
  }

  /**
   * Preview add field operation.
   */
  public function previewAddField(array $params): array {
    $entityType = $params['entity_type'] ?? 'node';
    $bundle = $params['bundle'] ?? '';
    $fieldName = $params['field_name'] ?? $params['name'] ?? '';
    $fieldType = $params['field_type'] ?? $params['type'] ?? 'string';

    if (empty($bundle) || empty($fieldName)) {
      return [
        'success' => FALSE,
        'error' => 'bundle and field_name parameters are required for add_field preview.',
      ];
    }

    $fullFieldName = str_starts_with($fieldName, 'field_') ? $fieldName : "field_$fieldName";

    $storageConfig = "field.storage.$entityType.$fullFieldName";
    $fieldConfig = "field.field.$entityType.$bundle.$fullFieldName";

    $storageExists = $this->activeStorage->read($storageConfig) !== FALSE;
    $fieldExists = $this->activeStorage->read($fieldConfig) !== FALSE;

    return [
      'success' => TRUE,
      'data' => [
        'action' => 'Add field',
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'field_name' => $fullFieldName,
        'field_type' => $fieldType,
        'storage_exists' => $storageExists,
        'field_exists' => $fieldExists,
        'configs_created' => array_filter([
          $storageExists ? NULL : $storageConfig,
          $fieldExists ? NULL : $fieldConfig,
        ]),
        'description' => $fieldExists
          ? "Field '$fullFieldName' already exists on $entityType.$bundle."
          : sprintf(
            "Would create %s field '$fullFieldName' on $entityType.$bundle.",
            $storageExists ? 'instance of existing' : 'new'
          ),
      ],
    ];
  }

  /**
   * Preview delete field operation.
   */
  public function previewDeleteField(array $params): array {
    $entityType = $params['entity_type'] ?? 'node';
    $bundle = $params['bundle'] ?? '';
    $fieldName = $params['field_name'] ?? $params['name'] ?? '';

    if ($bundle === '' || $fieldName === '') {
      return [
        'success' => FALSE,
        'error' => 'bundle and field_name parameters are required for delete_field preview.',
      ];
    }

    $fullFieldName = str_starts_with($fieldName, 'field_') ? $fieldName : "field_$fieldName";

    $storageConfig = "field.storage.$entityType.$fullFieldName";
    $fieldConfig = "field.field.$entityType.$bundle.$fullFieldName";

    $storageExists = $this->activeStorage->read($storageConfig) !== FALSE;
    $fieldExists = $this->activeStorage->read($fieldConfig) !== FALSE;

    return [
      'success' => TRUE,
      'data' => [
        'action' => 'Delete field instance',
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'field_name' => $fullFieldName,
        'field_exists' => $fieldExists,
        'storage_exists' => $storageExists,
        'configs_deleted' => $fieldExists ? [$fieldConfig] : [],
        'description' => $fieldExists
          ? "Would delete field '$fullFieldName' from $entityType.$bundle. Field storage may remain if used elsewhere."
          : "Field '$fullFieldName' was not found on $entityType.$bundle. No action would be taken.",
      ],
    ];
  }

  /**
   * Preview create vocabulary operation.
   */
  public function previewCreateVocabulary(array $params): array {
    $machineName = $params['machine_name'] ?? $params['vid'] ?? '';
    $name = $params['name'] ?? '';

    if (empty($machineName)) {
      return [
        'success' => FALSE,
        'error' => 'machine_name parameter is required for create_vocabulary preview.',
      ];
    }

    $configName = "taxonomy.vocabulary.$machineName";
    $exists = $this->activeStorage->read($configName) !== FALSE;

    return [
      'success' => TRUE,
      'data' => [
        'action' => 'Create vocabulary',
        'machine_name' => $machineName,
        'name' => $name ?: $machineName,
        'already_exists' => $exists,
        'configs_created' => [$configName],
        'description' => $exists
          ? "Vocabulary '$machineName' already exists. Operation would fail or update existing."
          : "Would create new vocabulary '$machineName'.",
      ],
    ];
  }

  /**
   * Preview create view operation.
   */
  public function previewCreateView(array $params): array {
    $viewId = $params['id'] ?? $params['view_id'] ?? '';
    $label = $params['label'] ?? '';

    if (empty($viewId)) {
      return [
        'success' => FALSE,
        'error' => 'id parameter is required for create_view preview.',
      ];
    }

    $configName = "views.view.$viewId";
    $exists = $this->activeStorage->read($configName) !== FALSE;

    return [
      'success' => TRUE,
      'data' => [
        'action' => 'Create view',
        'view_id' => $viewId,
        'label' => $label ?: $viewId,
        'already_exists' => $exists,
        'configs_created' => [$configName],
        'description' => $exists
          ? "View '$viewId' already exists. Operation would fail or update existing."
          : "Would create new view '$viewId'.",
      ],
    ];
  }

  /**
   * Find configurations that depend on the given config.
   *
   * @param string $configName
   *   The configuration name.
   *
   * @return array
   *   List of dependent config names.
   */
  protected function findDependents(string $configName): array {
    $dependents = [];

    foreach ($this->activeStorage->listAll() as $name) {
      $data = $this->activeStorage->read($name);
      if ($data && isset($data['dependencies'])) {
        $deps = $data['dependencies'];
        if (isset($deps['config']) && in_array($configName, $deps['config'], TRUE)) {
          $dependents[] = $name;
        }
      }
    }

    return $dependents;
  }

}
