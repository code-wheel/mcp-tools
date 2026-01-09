<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_config\Service;

use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Service for comparing and diffing configuration.
 */
class ConfigComparisonService {

  public function __construct(
    protected StorageInterface $activeStorage,
    protected StorageInterface $syncStorage,
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
   * Preview import config operation.
   */
  public function previewImportConfig(): array {
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
  public function generateDiff(array $old, array $new, string $prefix = ''): array {
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

}
