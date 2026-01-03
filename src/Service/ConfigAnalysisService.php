<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageComparer;

/**
 * Service for analyzing configuration.
 */
class ConfigAnalysisService {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected StorageInterface $activeStorage,
    protected StorageInterface $syncStorage,
  ) {}

  /**
   * Get configuration sync status.
   *
   * @return array
   *   Configuration sync status data.
   */
  public function getConfigStatus(): array {
    try {
      $storageComparer = new StorageComparer(
        $this->syncStorage,
        $this->activeStorage
      );

      $hasChanges = $storageComparer->createChangelist()->hasChanges();
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
                  'collection' => $collection,
                ];
              }
            }
          }
        }
      }

      return [
        'has_changes' => $hasChanges,
        'total_changes' => count($changelist),
        'changes' => $changelist,
        'sync_directory_exists' => !empty($this->syncStorage->listAll()),
      ];
    }
    catch (\Exception $e) {
      return [
        'error' => 'Unable to compare configuration: ' . $e->getMessage(),
        'has_changes' => FALSE,
      ];
    }
  }

  /**
   * Get a specific configuration object.
   *
   * @param string $name
   *   Configuration name (e.g., 'system.site').
   *
   * @return array
   *   Configuration data.
   */
  public function getConfig(string $name): array {
    $config = $this->configFactory->get($name);

    if ($config->isNew()) {
      return [
        'error' => "Configuration '$name' does not exist.",
      ];
    }

    $data = $config->getRawData();

    // SECURITY: Avoid leaking secrets by default.
    // Allow full output only when explicitly enabled in settings.
    $settings = $this->configFactory->get('mcp_tools.settings');
    $includeSensitive = (bool) ($settings->get('output.include_sensitive') ?? FALSE);
    if (!$includeSensitive) {
      $data = $this->sanitizeConfigData($data);
    }

    return [
      'name' => $name,
      'data' => $data,
    ];
  }

  /**
   * Redact sensitive keys from configuration arrays.
   *
   * @param array $data
   *   Raw configuration data.
   *
   * @return array
   *   Sanitized configuration data.
   */
  protected function sanitizeConfigData(array $data): array {
    $sensitiveKeys = [
      'password',
      'pass',
      'secret',
      'token',
      'key',
      'credential',
      'credentials',
      'api_key',
      'apikey',
      'client_secret',
      'private',
    ];

    $sanitized = $data;
    array_walk_recursive($sanitized, function (&$value, $key) use ($sensitiveKeys) {
      foreach ($sensitiveKeys as $sensitiveKey) {
        if (stripos((string) $key, $sensitiveKey) !== FALSE) {
          $value = '[REDACTED]';
          return;
        }
      }
    });

    return $sanitized;
  }

  /**
   * List all configuration names.
   *
   * @param string|null $prefix
   *   Optional prefix filter (e.g., 'system.' or 'node.type.').
   *
   * @return array
   *   List of configuration names.
   */
  public function listConfig(?string $prefix = NULL): array {
    $names = $this->activeStorage->listAll($prefix ?? '');

    return [
      'prefix' => $prefix,
      'total' => count($names),
      'names' => $names,
    ];
  }

  /**
   * Get configuration overrides.
   *
   * @return array
   *   List of overridden configuration.
   */
  public function getOverrides(): array {
    $overrides = [];

    // Check common configuration that might be overridden.
    $configsToCheck = [
      'system.site',
      'system.performance',
      'system.logging',
      'system.mail',
    ];

    foreach ($configsToCheck as $name) {
      $config = $this->configFactory->get($name);
      if ($config->hasOverrides()) {
        $overrides[] = [
          'name' => $name,
          'has_overrides' => TRUE,
        ];
      }
    }

    return [
      'total_checked' => count($configsToCheck),
      'overridden_count' => count($overrides),
      'overridden' => $overrides,
      'note' => 'Only common configurations are checked. Use $settings or $config overrides in settings.php.',
    ];
  }

}
