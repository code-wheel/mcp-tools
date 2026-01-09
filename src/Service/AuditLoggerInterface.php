<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

/**
 * Interface for MCP audit logging services.
 *
 * Provides logging for MCP write operations across all submodules.
 */
interface AuditLoggerInterface {

  /**
   * Log a write operation.
   *
   * @param string $operation
   *   The operation type (e.g., 'create_content_type', 'add_field').
   * @param string $entityType
   *   The entity type being modified.
   * @param string $entityId
   *   The entity ID or identifier.
   * @param array $details
   *   Additional details about the operation.
   * @param bool $success
   *   Whether the operation succeeded.
   */
  public function log(string $operation, string $entityType, string $entityId, array $details = [], bool $success = TRUE): void;

  /**
   * Log a successful operation.
   *
   * @param string $operation
   *   The operation type.
   * @param string $entityType
   *   The entity type being modified.
   * @param string $entityId
   *   The entity ID or identifier.
   * @param array $details
   *   Additional details about the operation.
   */
  public function logSuccess(string $operation, string $entityType, string $entityId, array $details = []): void;

  /**
   * Log a failed operation.
   *
   * @param string $operation
   *   The operation type.
   * @param string $entityType
   *   The entity type being modified.
   * @param string $entityId
   *   The entity ID or identifier.
   * @param array $details
   *   Additional details about the operation.
   */
  public function logFailure(string $operation, string $entityType, string $entityId, array $details = []): void;

}
