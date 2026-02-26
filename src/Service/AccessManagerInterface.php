<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

/**
 * Interface for MCP access control services.
 *
 * Provides three layers of access control:
 * 1. Module-based: Only installed modules' tools are available
 * 2. Global read-only mode: Site-wide toggle to block all writes
 * 3. Connection scopes: Per-connection access levels (read, write, admin)
 */
interface AccessManagerInterface {

  /**
   * Available scopes.
   */
  public const SCOPE_READ = 'read';
  public const SCOPE_WRITE = 'write';
  public const SCOPE_ADMIN = 'admin';

  /**
   * Write kinds (used for config-only mode).
   */
  public const WRITE_KIND_CONFIG = 'config';
  public const WRITE_KIND_CONTENT = 'content';
  public const WRITE_KIND_OPS = 'ops';

  /**
   * All available scopes.
   */
  public const ALL_SCOPES = [
    self::SCOPE_READ,
    self::SCOPE_WRITE,
    self::SCOPE_ADMIN,
  ];

  /**
   * All available write kinds.
   */
  public const ALL_WRITE_KINDS = [
    self::WRITE_KIND_CONFIG,
    self::WRITE_KIND_CONTENT,
    self::WRITE_KIND_OPS,
  ];

  /**
   * Check if read operations are allowed.
   *
   * @return bool
   *   TRUE if read operations are allowed.
   */
  public function canRead(): bool;

  /**
   * Check if config-only mode is enabled.
   *
   * @return bool
   *   TRUE if config-only mode is enabled.
   */
  public function isConfigOnlyMode(): bool;

  /**
   * Check if a given write kind is allowed under config-only policy.
   *
   * @param string $kind
   *   One of: config, content, ops.
   *
   * @return bool
   *   TRUE if the write kind is allowed.
   */
  public function isWriteKindAllowed(string $kind): bool;

  /**
   * Check if write operations are allowed.
   *
   * @param string $operationType
   *   The type of operation for rate limiting: 'write', 'delete', 'structure'.
   *
   * @return bool
   *   TRUE if write operations are allowed.
   */
  public function canWrite(string $operationType = 'write'): bool;

  /**
   * Check if admin operations are allowed.
   *
   * @param string $operationType
   *   The type of operation for rate limiting: 'admin', 'structure'.
   *
   * @return bool
   *   TRUE if admin operations are allowed.
   */
  public function canAdmin(string $operationType = 'structure'): bool;

  /**
   * Check if a specific scope is available.
   *
   * @param string $scope
   *   The scope to check.
   *
   * @return bool
   *   TRUE if the scope is available.
   */
  public function hasScope(string $scope): bool;

  /**
   * Get the current connection's scopes.
   *
   * @return array
   *   Array of scope strings.
   */
  public function getCurrentScopes(): array;

  /**
   * Set the current scopes (for testing or programmatic use).
   *
   * @param array $scopes
   *   Array of scope strings.
   */
  public function setScopes(array $scopes): void;

  /**
   * Convenience access check for write/admin tools.
   *
   * @param string $operation
   *   A high-level operation label
   *   (e.g., create, update, delete, clear, admin).
   * @param string $entityType
   *   The entity type being modified (context only).
   *
   * @return array
   *   Array with keys:
   *   - allowed: bool
   *   - reason: string|null
   *   - code: string|null
   *   - retry_after: int|null
   */
  public function checkWriteAccess(string $operation, string $entityType): array;

  /**
   * Get access denied response for read operations.
   *
   * @return array
   *   Error response array with keys: success, error, code.
   */
  public function getReadAccessDenied(): array;

  /**
   * Get access denied response for write operations.
   *
   * @return array
   *   Error response array with keys: success, error, code,
   *   and optionally retry_after.
   */
  public function getWriteAccessDenied(): array;

  /**
   * Check if global read-only mode is enabled.
   *
   * @return bool
   *   TRUE if read-only mode is enabled.
   */
  public function isReadOnlyMode(): bool;

  /**
   * Get the last rate limit error.
   *
   * @return array|null
   *   Rate limit error details or NULL.
   */
  public function getLastRateLimitError(): ?array;

  /**
   * Clear the last rate limit error.
   */
  public function clearRateLimitError(): void;

}
