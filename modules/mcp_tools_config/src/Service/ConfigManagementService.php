<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_config\Service;

use Drupal\Core\Config\StorageInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Facade service for configuration management operations.
 *
 * Addresses config drift by tracking MCP-created configuration changes
 * and providing tools to export configuration.
 */
class ConfigManagementService {

  public function __construct(
    protected StorageInterface $activeStorage,
    protected StorageInterface $syncStorage,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
    protected ConfigComparisonService $configComparisonService,
    protected McpChangeTracker $mcpChangeTracker,
    protected OperationPreviewService $operationPreviewService,
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
    return $this->configComparisonService->getConfigChanges();
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
      $this->mcpChangeTracker->clearMcpChanges();

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
    return $this->mcpChangeTracker->getMcpChanges();
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
    return $this->configComparisonService->getConfigDiff($configName);
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
    return $this->operationPreviewService->previewOperation($operation, $params);
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
    $this->mcpChangeTracker->trackChange($configName, $operation);
  }

}
