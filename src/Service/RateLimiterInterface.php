<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

/**
 * Interface for MCP rate limiting services.
 *
 * Provides configurable rate limiting to prevent abuse:
 * - Per-minute limits
 * - Per-hour limits
 * - Per-operation type limits.
 */
interface RateLimiterInterface {

  /**
   * Check if a write operation is allowed.
   *
   * @param string $operationType
   *   The type of operation: 'write', 'delete', 'structure', 'admin'.
   *
   * @return array
   *   Array with keys:
   *   - allowed: bool - Whether the operation is allowed.
   *   - error: string|null - Error message if not allowed.
   *   - retry_after: int|null - Seconds until retry is allowed.
   */
  public function checkLimit(string $operationType = 'write'): array;

  /**
   * Check if an expensive read operation is allowed.
   *
   * These limits are separate from write operation rate limiting and are meant
   * to protect site performance from expensive read scans.
   *
   * @param string $operation
   *   Read operation key (e.g., 'broken_link_scan', 'content_search').
   *
   * @return array
   *   Array with keys:
   *   - allowed: bool - Whether the operation is allowed.
   *   - error: string|null - Error message if not allowed.
   *   - retry_after: int|null - Seconds until retry is allowed.
   *   - code: string|null - Error code if not allowed.
   */
  public function checkReadLimit(string $operation): array;

  /**
   * Get current rate limit status for a client.
   *
   * @return array
   *   Current usage statistics including:
   *   - enabled: bool - Whether rate limiting is enabled.
   *   - client_id: string - Truncated client identifier.
   *   - limits: array - Configured limits.
   *   - current_usage: array - Current usage by operation type.
   */
  public function getStatus(): array;

  /**
   * Reset rate limits for a client (admin function).
   *
   * @param string|null $clientId
   *   Client ID to reset, or NULL for current client.
   */
  public function resetLimits(?string $clientId = NULL): void;

}
