<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use CodeWheel\McpErrorCodes\ErrorCode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\State\StateInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for rate limiting MCP write operations.
 *
 * Provides configurable rate limiting to prevent abuse:
 * - Per-minute limits
 * - Per-hour limits
 * - Per-operation type limits.
 */
class RateLimiter implements RateLimiterInterface {

  /**
   * Default rate limits.
   */
  protected const DEFAULT_LIMITS = [
    'max_writes_per_minute' => 30,
    'max_writes_per_hour' => 500,
    'max_deletes_per_hour' => 50,
    'max_structure_changes_per_hour' => 100,
  ];

  /**
   * State keys prefix.
   */
  protected const STATE_PREFIX = 'mcp_tools.rate_limit.';

  /**
   * State keys prefix for read operation limits.
   */
  protected const READ_STATE_PREFIX = 'mcp_tools.read_rate_limit.';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected StateInterface $state,
    protected RequestStack $requestStack,
    protected ?PrivateKey $privateKey = NULL,
  ) {}

  /**
   * Check if a write operation is allowed.
   *
   * @param string $operationType
   *   The type of operation: 'write', 'delete', 'structure', 'admin'.
   *
   * @return array
   *   ['allowed' => bool, 'error' => string|null, 'retry_after' => int|null]
   */
  public function checkLimit(string $operationType = 'write'): array {
    $config = $this->configFactory->get('mcp_tools.settings');

    // Check if rate limiting is enabled.
    if (!$config->get('rate_limiting.enabled')) {
      return ['allowed' => TRUE, 'error' => NULL, 'retry_after' => NULL];
    }

    $operationType = strtolower(trim($operationType));
    // Treat "admin" operations as "structure" changes
    // (most restrictive bucket).
    if ($operationType === 'admin') {
      $operationType = 'structure';
    }

    $clientId = $this->getClientIdentifier();
    $now = time();

    // Always enforce the global write limits for *all* operation types.
    $minuteCheck = $this->checkWindow($clientId, 'minute', 60, 'write', $now);
    if (!$minuteCheck['allowed']) {
      return $minuteCheck;
    }

    $hourCheck = $this->checkWindow($clientId, 'hour', 3600, 'write', $now);
    if (!$hourCheck['allowed']) {
      return $hourCheck;
    }

    // Apply per-operation type limits (in addition to global write limits).
    if ($operationType === 'delete') {
      $deleteCheck = $this->checkWindow($clientId, 'hour', 3600, 'delete', $now);
      if (!$deleteCheck['allowed']) {
        return $deleteCheck;
      }
    }

    if ($operationType === 'structure') {
      $structureCheck = $this->checkWindow($clientId, 'hour', 3600, 'structure', $now);
      if (!$structureCheck['allowed']) {
        return $structureCheck;
      }
    }

    // Record this operation.
    $this->recordOperation($clientId, $operationType, $now);

    return ['allowed' => TRUE, 'error' => NULL, 'retry_after' => NULL];
  }

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
   *   Array with keys: allowed (bool), error (string|null),
   *   retry_after (int|null), code (string|null).
   */
  public function checkReadLimit(string $operation): array {
    $operation = strtolower(trim($operation));
    $config = $this->configFactory->get('mcp_tools.settings');

    // Resolve per-operation window and limit.
    $window = NULL;
    $windowSeconds = NULL;
    $limit = NULL;

    if ($operation === 'broken_link_scan') {
      $window = 'hour';
      $windowSeconds = 3600;
      $limit = (int) ($config->get('rate_limits.broken_link_scan.max_per_hour') ?? 10);
    }
    elseif ($operation === 'content_search') {
      $window = 'minute';
      $windowSeconds = 60;
      $limit = (int) ($config->get('rate_limits.content_search.max_per_minute') ?? 30);
    }
    else {
      // Unknown operation: do not rate limit by default.
      return ['allowed' => TRUE, 'error' => NULL, 'retry_after' => NULL, 'code' => NULL];
    }

    // Treat non-positive limits as unlimited.
    if ($limit <= 0) {
      return ['allowed' => TRUE, 'error' => NULL, 'retry_after' => NULL, 'code' => NULL];
    }

    $clientId = $this->getClientIdentifier();
    $now = time();

    $stateKey = self::READ_STATE_PREFIX . "$clientId.$operation.$window";
    $windowData = $this->state->get($stateKey, ['count' => 0, 'window_start' => $now]);

    if ($now - $windowData['window_start'] >= $windowSeconds) {
      $windowData = ['count' => 0, 'window_start' => $now];
    }

    if ($windowData['count'] >= $limit) {
      $retryAfter = $windowData['window_start'] + $windowSeconds - $now;
      return [
        'allowed' => FALSE,
        'error' => "Rate limit exceeded: Maximum $limit $operation operations per $window. Try again in $retryAfter seconds.",
        'retry_after' => $retryAfter,
        'code' => ErrorCode::RATE_LIMIT_EXCEEDED,
      ];
    }

    $windowData['count']++;
    $this->state->set($stateKey, $windowData);

    return ['allowed' => TRUE, 'error' => NULL, 'retry_after' => NULL, 'code' => NULL];
  }

  /**
   * Check rate limit for a specific time window.
   *
   * @param string $clientId
   *   Client identifier.
   * @param string $window
   *   Window name ('minute' or 'hour').
   * @param int $windowSeconds
   *   Window duration in seconds.
   * @param string $operationType
   *   Operation type.
   * @param int $now
   *   Current timestamp.
   *
   * @return array
   *   Check result.
   */
  protected function checkWindow(string $clientId, string $window, int $windowSeconds, string $operationType, int $now): array {
    $config = $this->configFactory->get('mcp_tools.settings');

    // Get the appropriate limit.
    $limitKey = $this->getLimitKey($operationType, $window);
    $limit = $config->get("rate_limiting.$limitKey") ?? self::DEFAULT_LIMITS[$limitKey] ?? PHP_INT_MAX;

    // Get current count for this window.
    $stateKey = self::STATE_PREFIX . "$clientId.$operationType.$window";
    $windowData = $this->state->get($stateKey, ['count' => 0, 'window_start' => $now]);

    // Check if we're in a new window.
    if ($now - $windowData['window_start'] >= $windowSeconds) {
      // Reset the window.
      $windowData = ['count' => 0, 'window_start' => $now];
      $this->state->set($stateKey, $windowData);
    }

    // Check if limit exceeded.
    if ($windowData['count'] >= $limit) {
      $retryAfter = $windowData['window_start'] + $windowSeconds - $now;
      return [
        'allowed' => FALSE,
        'error' => "Rate limit exceeded: Maximum $limit $operationType operations per $window. Try again in $retryAfter seconds.",
        'retry_after' => $retryAfter,
        'code' => ErrorCode::RATE_LIMIT_EXCEEDED,
      ];
    }

    return ['allowed' => TRUE, 'error' => NULL, 'retry_after' => NULL];
  }

  /**
   * Record an operation for rate limiting.
   *
   * @param string $clientId
   *   Client identifier.
   * @param string $operationType
   *   Operation type.
   * @param int $now
   *   Current timestamp.
   */
  protected function recordOperation(string $clientId, string $operationType, int $now): void {
    // Update minute window.
    $this->incrementWindow($clientId, $operationType, 'minute', 60, $now);

    // Update hour window.
    $this->incrementWindow($clientId, $operationType, 'hour', 3600, $now);

    // Also track general 'write' for specific types.
    if ($operationType !== 'write') {
      $this->incrementWindow($clientId, 'write', 'minute', 60, $now);
      $this->incrementWindow($clientId, 'write', 'hour', 3600, $now);
    }
  }

  /**
   * Increment counter for a rate limit window.
   *
   * @param string $clientId
   *   Client identifier.
   * @param string $operationType
   *   Operation type.
   * @param string $window
   *   Window name.
   * @param int $windowSeconds
   *   Window duration.
   * @param int $now
   *   Current timestamp.
   */
  protected function incrementWindow(string $clientId, string $operationType, string $window, int $windowSeconds, int $now): void {
    $stateKey = self::STATE_PREFIX . "$clientId.$operationType.$window";
    $windowData = $this->state->get($stateKey, ['count' => 0, 'window_start' => $now]);

    // Check if we're in a new window.
    if ($now - $windowData['window_start'] >= $windowSeconds) {
      $windowData = ['count' => 0, 'window_start' => $now];
    }

    $windowData['count']++;
    $this->state->set($stateKey, $windowData);
  }

  /**
   * Get the limit configuration key for an operation type and window.
   *
   * @param string $operationType
   *   Operation type.
   * @param string $window
   *   Window name.
   *
   * @return string
   *   Configuration key.
   */
  protected function getLimitKey(string $operationType, string $window): string {
    return match ($operationType) {
      'delete' => 'max_deletes_per_hour',
      'structure' => 'max_structure_changes_per_hour',
      default => $window === 'minute' ? 'max_writes_per_minute' : 'max_writes_per_hour',
    };
  }

  /**
   * Get a unique identifier for the current client/connection.
   *
   * SECURITY: This identifier is used for rate limiting. We use multiple
   * factors to make spoofing more difficult:
   * - HTTP: IP address + optional client ID header
   * - HTTP (trusted): IP address + server-provided client ID attribute
   * - CLI: Process ID + parent PID + user ID (harder to spoof than env vars)
   *
   * @return string
   *   Client identifier (SHA-256 hash).
   */
  protected function getClientIdentifier(): string {
    $request = $this->requestStack->getCurrentRequest();

    if ($request) {
      // Use combination of IP and any client ID header.
      $ip = $request->getClientIp() ?? 'unknown';
      $fingerprint = $request->headers->get('User-Agent', '') . ':' .
                     $request->headers->get('Accept-Language', '');

      $trustedClientId = '';
      $attributes = $request->attributes ?? NULL;
      if ($attributes && method_exists($attributes, 'get')) {
        $trustedClientId = (string) $attributes->get('mcp_tools.client_id', '');
      }

      $trustHeader = (bool) $this->configFactory->get('mcp_tools.settings')->get('rate_limiting.trust_client_id_header');
      $headerClientId = $trustHeader ? (string) $request->headers->get('X-MCP-Client-Id', '') : '';

      $clientId = $trustedClientId !== '' ? $trustedClientId : $headerClientId;

      if ($clientId) {
        return hash('sha256', $ip . ':' . $clientId . ':' . $fingerprint);
      }

      return hash('sha256', $ip . ':' . $fingerprint);
    }

    // SECURITY: For CLI/STDIO transport, use system-level identifiers
    // that are harder to spoof than environment variables.
    // Combine: process ID, parent process ID, and effective user ID.
    $processId = getmypid();
    $parentPid = function_exists('posix_getppid') ? posix_getppid() : 0;
    $userId = function_exists('posix_geteuid') ? posix_geteuid() : 0;

    // Include a machine-specific secret if available
    // (from Drupal's private key).
    $privateKeyValue = '';
    if ($this->privateKey !== NULL) {
      try {
        $privateKeyValue = $this->privateKey->get() ?? '';
      }
      catch (\Exception $e) {
        // Ignore if service unavailable.
      }
    }

    $cliIdentifier = sprintf(
      'cli:%d:%d:%d:%s',
      $processId,
      $parentPid,
      $userId,
      substr(hash('sha256', $privateKeyValue), 0, 16)
    );

    return hash('sha256', $cliIdentifier);
  }

  /**
   * Get current rate limit status for a client.
   *
   * @return array
   *   Current usage statistics.
   */
  public function getStatus(): array {
    $config = $this->configFactory->get('mcp_tools.settings');
    $clientId = $this->getClientIdentifier();
    $now = time();

    $status = [
      'enabled' => (bool) $config->get('rate_limiting.enabled'),
      'client_id' => substr($clientId, 0, 12) . '...',
      'limits' => [
        'writes_per_minute' => $config->get('rate_limiting.max_writes_per_minute') ?? self::DEFAULT_LIMITS['max_writes_per_minute'],
        'writes_per_hour' => $config->get('rate_limiting.max_writes_per_hour') ?? self::DEFAULT_LIMITS['max_writes_per_hour'],
        'deletes_per_hour' => $config->get('rate_limiting.max_deletes_per_hour') ?? self::DEFAULT_LIMITS['max_deletes_per_hour'],
        'structure_changes_per_hour' => $config->get('rate_limiting.max_structure_changes_per_hour') ?? self::DEFAULT_LIMITS['max_structure_changes_per_hour'],
      ],
      'current_usage' => [],
    ];

    if ($status['enabled']) {
      // Get current counts.
      foreach (['write', 'delete', 'structure'] as $opType) {
        $minuteKey = self::STATE_PREFIX . "$clientId.$opType.minute";
        $hourKey = self::STATE_PREFIX . "$clientId.$opType.hour";

        $minuteData = $this->state->get($minuteKey, ['count' => 0, 'window_start' => $now]);
        $hourData = $this->state->get($hourKey, ['count' => 0, 'window_start' => $now]);

        // Reset if window expired.
        if ($now - $minuteData['window_start'] >= 60) {
          $minuteData = ['count' => 0, 'window_start' => $now];
        }
        if ($now - $hourData['window_start'] >= 3600) {
          $hourData = ['count' => 0, 'window_start' => $now];
        }

        $status['current_usage'][$opType] = [
          'minute' => $minuteData['count'],
          'hour' => $hourData['count'],
        ];
      }
    }

    return $status;
  }

  /**
   * Reset rate limits for a client (admin function).
   *
   * @param string|null $clientId
   *   Client ID to reset, or NULL for current client.
   */
  public function resetLimits(?string $clientId = NULL): void {
    $clientId = $clientId ?? $this->getClientIdentifier();

    foreach (['write', 'delete', 'structure'] as $opType) {
      foreach (['minute', 'hour'] as $window) {
        $stateKey = self::STATE_PREFIX . "$clientId.$opType.$window";
        $this->state->delete($stateKey);
      }
    }
  }

}
