<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_config\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Symfony\Component\Yaml\Yaml;

/**
 * Service for configuration management operations.
 *
 * Addresses config drift by tracking MCP-created configuration changes
 * and providing tools to export configuration.
 */
class ConfigManagementService {

  /**
   * State key for tracking MCP configuration changes.
   */
  protected const STATE_KEY = 'mcp_tools.config_changes';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected StorageInterface $activeStorage,
    protected StorageInterface $syncStorage,
    protected StateInterface $state,
    protected FileSystemInterface $fileSystem,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Get configuration changes between active and sync storage.
   *
   * Lists config that differs from sync directory (what would be exported).
   *
   * @return array
   *   Result array with changes information.
   */
  public function getConfigChanges(): array {
    try {
      $storageComparer = new StorageComparer(
        $this->activeStorage,
        $this->syncStorage
      );

      $storageComparer->createChangelist();
      $hasChanges = $storageComparer->hasChanges();
      $changelist = [];

      if ($hasChanges) {
        foreach ($storageComparer->getAllCollectionNames() as $collection) {
          foreach (['create', 'update', 'delete', 'rename'] as $op) {
            $changes = $storageComparer->getChangelist($op, $collection);
            if (!empty($changes)) {
              foreach ($changes as $name) {
                $changelist[] = [
                  'name' => $name,
                  'operation' => $op,
                  'collection' => $collection ?: 'default',
                ];
              }
            }
          }
        }
      }

      // Group changes by operation for easier reading.
      $grouped = [
        'create' => [],
        'update' => [],
        'delete' => [],
        'rename' => [],
      ];

      foreach ($changelist as $change) {
        $grouped[$change['operation']][] = $change['name'];
      }

      return [
        'success' => TRUE,
        'data' => [
          'has_changes' => $hasChanges,
          'total_changes' => count($changelist),
          'summary' => [
            'new_in_active' => count($grouped['create']),
            'modified' => count($grouped['update']),
            'deleted_from_active' => count($grouped['delete']),
            'renamed' => count($grouped['rename']),
          ],
          'changes' => $grouped,
          'message' => $hasChanges
            ? sprintf('%d configuration changes detected that would be exported.', count($changelist))
            : 'Active configuration matches sync directory.',
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Unable to compare configuration: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Export configuration to sync directory.
   *
   * This runs the equivalent of drush config:export.
   *
   * @return array
   *   Result array with export information.
   */
  public function exportConfig(): array {
    if (!$this->accessManager->canAdmin()) {
      return [
        'success' => FALSE,
        'error' => 'Admin scope required for configuration export.',
        'code' => 'INSUFFICIENT_SCOPE',
      ];
    }

    try {
      // First, get the changes that will be exported.
      $changesResult = $this->getConfigChanges();
      if (!$changesResult['success']) {
        return $changesResult;
      }

      $changesBefore = $changesResult['data']['total_changes'];

      if ($changesBefore === 0) {
        return [
          'success' => TRUE,
          'data' => [
            'exported' => 0,
            'message' => 'No changes to export. Active configuration already matches sync directory.',
          ],
        ];
      }

      // Export all configuration from active to sync storage.
      $exported = 0;
      $errors = [];

      // Get all config names from active storage.
      $activeNames = $this->activeStorage->listAll();

      foreach ($activeNames as $name) {
        $data = $this->activeStorage->read($name);
        if ($data !== FALSE) {
          try {
            $this->syncStorage->write($name, $data);
            $exported++;
          }
          catch (\Exception $e) {
            $errors[] = sprintf('%s: %s', $name, $e->getMessage());
          }
        }
      }

      // Delete configs from sync that don't exist in active.
      $syncNames = $this->syncStorage->listAll();
      $toDelete = array_diff($syncNames, $activeNames);
      $deleted = 0;

      foreach ($toDelete as $name) {
        try {
          $this->syncStorage->delete($name);
          $deleted++;
        }
        catch (\Exception $e) {
          $errors[] = sprintf('Delete %s: %s', $name, $e->getMessage());
        }
      }

      $this->auditLogger->logSuccess('export_config', 'config', 'all', [
        'exported' => $exported,
        'deleted' => $deleted,
        'changes_before' => $changesBefore,
      ]);

      // Clear MCP tracked changes since we've exported.
      $this->clearMcpChanges();

      $result = [
        'success' => TRUE,
        'data' => [
          'exported' => $exported,
          'deleted_from_sync' => $deleted,
          'changes_resolved' => $changesBefore,
          'message' => sprintf(
            'Configuration exported successfully. %d configs written, %d deleted from sync.',
            $exported,
            $deleted
          ),
        ],
      ];

      if (!empty($errors)) {
        $result['data']['errors'] = $errors;
        $result['data']['warning'] = 'Some configs could not be exported.';
      }

      return $result;
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('export_config', 'config', 'all', ['error' => $e->getMessage()]);
      return [
        'success' => FALSE,
        'error' => 'Failed to export configuration: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Get configuration changes made via MCP.
   *
   * Returns config entities that were created/modified via MCP tools,
   * tracked in state.
   *
   * @return array
   *   Result array with MCP change information.
   */
  public function getMcpChanges(): array {
    $changes = $this->state->get(self::STATE_KEY, []);

    if (empty($changes)) {
      return [
        'success' => TRUE,
        'data' => [
          'total' => 0,
          'changes' => [],
          'message' => 'No configuration changes have been tracked via MCP.',
        ],
      ];
    }

    // Sort by timestamp, newest first.
    usort($changes, fn($a, $b) => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

    // Group by operation.
    $grouped = [];
    foreach ($changes as $change) {
      $op = $change['operation'] ?? 'unknown';
      if (!isset($grouped[$op])) {
        $grouped[$op] = [];
      }
      $grouped[$op][] = [
        'config_name' => $change['config_name'],
        'timestamp' => $change['timestamp'] ?? NULL,
        'human_time' => isset($change['timestamp'])
          ? date('Y-m-d H:i:s', $change['timestamp'])
          : 'unknown',
      ];
    }

    return [
      'success' => TRUE,
      'data' => [
        'total' => count($changes),
        'by_operation' => $grouped,
        'changes' => $changes,
        'message' => sprintf(
          '%d configuration changes tracked via MCP. Consider exporting to sync these changes.',
          count($changes)
        ),
      ],
    ];
  }

  /**
   * Get diff between active and sync configuration for a specific config.
   *
   * @param string $configName
   *   The configuration name to compare.
   *
   * @return array
   *   Result array with diff information.
   */
  public function getConfigDiff(string $configName): array {
    try {
      $activeData = $this->activeStorage->read($configName);
      $syncData = $this->syncStorage->read($configName);

      $existsInActive = $activeData !== FALSE;
      $existsInSync = $syncData !== FALSE;

      if (!$existsInActive && !$existsInSync) {
        return [
          'success' => FALSE,
          'error' => sprintf("Configuration '%s' does not exist in active or sync storage.", $configName),
        ];
      }

      if (!$existsInActive) {
        return [
          'success' => TRUE,
          'data' => [
            'config_name' => $configName,
            'status' => 'deleted_from_active',
            'message' => 'Configuration exists in sync but not in active storage (will be deleted on export).',
            'sync_data' => $syncData,
          ],
        ];
      }

      if (!$existsInSync) {
        return [
          'success' => TRUE,
          'data' => [
            'config_name' => $configName,
            'status' => 'new_in_active',
            'message' => 'Configuration exists in active but not in sync storage (will be created on export).',
            'active_data' => $activeData,
          ],
        ];
      }

      // Both exist, compare them.
      $activeYaml = Yaml::dump($activeData, 10, 2);
      $syncYaml = Yaml::dump($syncData, 10, 2);

      if ($activeYaml === $syncYaml) {
        return [
          'success' => TRUE,
          'data' => [
            'config_name' => $configName,
            'status' => 'unchanged',
            'message' => 'Configuration is identical in active and sync storage.',
          ],
        ];
      }

      // Generate a simple diff.
      $diff = $this->generateDiff($syncData, $activeData);

      return [
        'success' => TRUE,
        'data' => [
          'config_name' => $configName,
          'status' => 'modified',
          'message' => 'Configuration has been modified (active differs from sync).',
          'diff' => $diff,
          'sync_data' => $syncData,
          'active_data' => $activeData,
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to compare configuration: ' . $e->getMessage(),
      ];
    }
  }

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
      'create_content_type' => $this->previewCreateContentType($params),
      'add_field' => $this->previewAddField($params),
      'create_vocabulary' => $this->previewCreateVocabulary($params),
      'create_view' => $this->previewCreateView($params),
      default => [
        'success' => FALSE,
        'error' => sprintf(
          "Unknown operation '%s'. Supported: export_config, import_config, delete_config, create_content_type, add_field, create_vocabulary, create_view",
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
   * Track a configuration change made via MCP.
   *
   * Called by other services to track what config was created/modified via MCP.
   *
   * @param string $configName
   *   The configuration name that was changed.
   * @param string $operation
   *   The operation type (create, update, delete).
   */
  public function trackChange(string $configName, string $operation): void {
    $changes = $this->state->get(self::STATE_KEY, []);

    // Check if this config is already tracked.
    $existingIndex = NULL;
    foreach ($changes as $index => $change) {
      if ($change['config_name'] === $configName) {
        $existingIndex = $index;
        break;
      }
    }

    $changeRecord = [
      'config_name' => $configName,
      'operation' => $operation,
      'timestamp' => time(),
    ];

    if ($existingIndex !== NULL) {
      // Update existing entry.
      $changes[$existingIndex] = $changeRecord;
    }
    else {
      // Add new entry.
      $changes[] = $changeRecord;
    }

    // Keep only the last 500 changes to prevent unbounded growth.
    if (count($changes) > 500) {
      $changes = array_slice($changes, -500);
    }

    $this->state->set(self::STATE_KEY, $changes);
  }

  /**
   * Clear tracked MCP changes.
   */
  protected function clearMcpChanges(): void {
    $this->state->delete(self::STATE_KEY);
  }

  /**
   * Generate a simple diff between two arrays.
   *
   * @param array $old
   *   Old data.
   * @param array $new
   *   New data.
   * @param string $prefix
   *   Key prefix for nested paths.
   *
   * @return array
   *   Array of differences.
   */
  protected function generateDiff(array $old, array $new, string $prefix = ''): array {
    $diff = [];

    $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

    foreach ($allKeys as $key) {
      $path = $prefix ? "$prefix.$key" : $key;
      $oldExists = array_key_exists($key, $old);
      $newExists = array_key_exists($key, $new);

      if (!$oldExists && $newExists) {
        $diff[] = [
          'path' => $path,
          'type' => 'added',
          'new_value' => $new[$key],
        ];
      }
      elseif ($oldExists && !$newExists) {
        $diff[] = [
          'path' => $path,
          'type' => 'removed',
          'old_value' => $old[$key],
        ];
      }
      elseif (is_array($old[$key]) && is_array($new[$key])) {
        $nestedDiff = $this->generateDiff($old[$key], $new[$key], $path);
        $diff = array_merge($diff, $nestedDiff);
      }
      elseif ($old[$key] !== $new[$key]) {
        $diff[] = [
          'path' => $path,
          'type' => 'changed',
          'old_value' => $old[$key],
          'new_value' => $new[$key],
        ];
      }
    }

    return $diff;
  }

  /**
   * Preview export config operation.
   */
  protected function previewExportConfig(): array {
    $changes = $this->getConfigChanges();

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
  protected function previewImportConfig(): array {
    try {
      $storageComparer = new StorageComparer(
        $this->syncStorage,
        $this->activeStorage
      );

      $storageComparer->createChangelist();
      $hasChanges = $storageComparer->hasChanges();

      $changes = [
        'create' => [],
        'update' => [],
        'delete' => [],
      ];

      if ($hasChanges) {
        foreach ($storageComparer->getAllCollectionNames() as $collection) {
          foreach (['create', 'update', 'delete'] as $op) {
            $opChanges = $storageComparer->getChangelist($op, $collection);
            if (!empty($opChanges)) {
              $changes[$op] = array_merge($changes[$op], $opChanges);
            }
          }
        }
      }

      return [
        'success' => TRUE,
        'data' => [
          'action' => 'Import configuration from sync directory to active',
          'will_create' => count($changes['create']),
          'will_update' => count($changes['update']),
          'will_delete' => count($changes['delete']),
          'affected_configs' => $changes,
          'description' => $hasChanges
            ? 'This will import configuration from sync directory to active storage.'
            : 'No changes to import. Sync configuration matches active storage.',
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to preview import: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Preview delete config operation.
   */
  protected function previewDeleteConfig(string $configName): array {
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
   * Preview create content type operation.
   */
  protected function previewCreateContentType(array $params): array {
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
   * Preview add field operation.
   */
  protected function previewAddField(array $params): array {
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
   * Preview create vocabulary operation.
   */
  protected function previewCreateVocabulary(array $params): array {
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
  protected function previewCreateView(array $params): array {
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
