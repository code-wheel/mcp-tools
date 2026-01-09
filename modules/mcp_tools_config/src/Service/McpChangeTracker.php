<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_config\Service;

use Drupal\Core\State\StateInterface;

/**
 * Service for tracking configuration changes made via MCP.
 */
class McpChangeTracker {

  /**
   * State key for tracking MCP configuration changes.
   */
  protected const STATE_KEY = 'mcp_tools.config_changes';

  public function __construct(
    protected StateInterface $state,
  ) {}

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
   * Clear tracked MCP changes.
   */
  public function clearMcpChanges(): void {
    $this->state->delete(self::STATE_KEY);
  }

}
