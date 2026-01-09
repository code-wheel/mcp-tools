<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use CodeWheel\McpErrorCodes\ErrorCode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for managing MCP tool access control.
 *
 * Provides three layers of access control:
 * 1. Module-based: Only installed modules' tools are available
 * 2. Global read-only mode: Site-wide toggle to block all writes
 * 3. Connection scopes: Per-connection access levels (read, write, admin)
 */
class AccessManager implements AccessManagerInterface {

  /**
   * Current connection scope (set via request or drush option).
   */
  protected array $currentScopes = [];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected AccountProxyInterface $currentUser,
    protected RequestStack $requestStack,
    protected ?RateLimiter $rateLimiter = NULL,
  ) {}

  /**
   * Check if read operations are allowed.
   *
   * @return bool
   *   TRUE if read operations are allowed.
   */
  public function canRead(): bool {
    return $this->hasScope(self::SCOPE_READ);
  }

  /**
   * Check if config-only mode is enabled.
   */
  public function isConfigOnlyMode(): bool {
    return (bool) $this->configFactory->get('mcp_tools.settings')->get('access.config_only_mode');
  }

  /**
   * Check if a given write kind is allowed under the current config-only policy.
   *
   * When config-only mode is disabled, all write kinds are allowed (subject to
   * scopes/read-only mode and tool permissions).
   *
   * @param string $kind
   *   One of: config, content, ops.
   *
   * @return bool
   *   TRUE if the write kind is allowed.
   */
  public function isWriteKindAllowed(string $kind): bool {
    $config = $this->configFactory->get('mcp_tools.settings');

    if (!$config->get('access.config_only_mode')) {
      return TRUE;
    }

    $allowed = $config->get('access.config_only_allowed_write_kinds') ?? [self::WRITE_KIND_CONFIG];
    $allowed = array_values(array_intersect((array) $allowed, self::ALL_WRITE_KINDS));
    if (empty($allowed)) {
      $allowed = [self::WRITE_KIND_CONFIG];
    }

    return in_array($kind, $allowed, TRUE);
  }

  /**
   * Check if write operations are allowed.
   *
   * @param string $operationType
   *   The type of operation for rate limiting: 'write', 'delete', 'structure'.
   *
   * @return bool
   *   TRUE if write operations are allowed.
   */
  public function canWrite(string $operationType = 'write'): bool {
    $config = $this->configFactory->get('mcp_tools.settings');

    // Check global read-only mode first.
    if ($config->get('access.read_only_mode')) {
      return FALSE;
    }

    // Check scope.
    if (!$this->hasScope(self::SCOPE_WRITE)) {
      return FALSE;
    }

    // Check rate limit.
    if ($this->rateLimiter) {
      $rateCheck = $this->rateLimiter->checkLimit($operationType);
      if (!$rateCheck['allowed']) {
        // Store the rate limit error for retrieval.
        $this->lastRateLimitError = $rateCheck;
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Last rate limit error, if any.
   */
  protected ?array $lastRateLimitError = NULL;

  /**
   * Get the last rate limit error.
   *
   * @return array|null
   *   Rate limit error details or NULL.
   */
  public function getLastRateLimitError(): ?array {
    return $this->lastRateLimitError;
  }

  /**
   * Clear the last rate limit error.
   */
  public function clearRateLimitError(): void {
    $this->lastRateLimitError = NULL;
  }

  /**
   * Check if admin operations are allowed.
   *
   * Admin operations are rate-limited like write operations for consistency.
   *
   * @param string $operationType
   *   The type of operation for rate limiting: 'admin', 'structure'.
   *
   * @return bool
   *   TRUE if admin operations are allowed.
   */
  public function canAdmin(string $operationType = 'structure'): bool {
    $config = $this->configFactory->get('mcp_tools.settings');

    // Check global read-only mode first.
    if ($config->get('access.read_only_mode')) {
      return FALSE;
    }

    // Check scope.
    if (!$this->hasScope(self::SCOPE_ADMIN)) {
      return FALSE;
    }

    // SECURITY: Apply rate limiting to admin operations too.
    // Admin operations should have the same abuse prevention as writes.
    if ($this->rateLimiter) {
      $rateCheck = $this->rateLimiter->checkLimit($operationType);
      if (!$rateCheck['allowed']) {
        $this->lastRateLimitError = $rateCheck;
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Check if a specific scope is available.
   *
   * @param string $scope
   *   The scope to check.
   *
   * @return bool
   *   TRUE if the scope is available.
   */
  public function hasScope(string $scope): bool {
    $scopes = $this->getCurrentScopes();
    return in_array($scope, $scopes, TRUE);
  }

  /**
   * Get the current connection's scopes.
   *
   * @return array
   *   Array of scope strings.
   */
  public function getCurrentScopes(): array {
    if (!empty($this->currentScopes)) {
      return $this->currentScopes;
    }

    $config = $this->configFactory->get('mcp_tools.settings');

    // Maximum allowed scopes are always enforced.
    $allowedScopes = $config->get('access.allowed_scopes')
      ?? $config->get('access.default_scopes')
      ?? [self::SCOPE_READ];
    $allowedScopes = array_values(array_intersect($allowedScopes, self::ALL_SCOPES));
    if (empty($allowedScopes)) {
      // Always allow at least read; prevents accidental lockout.
      $allowedScopes = [self::SCOPE_READ];
    }

    // Default scopes (used when no trusted override is present).
    $defaultScopes = $config->get('access.default_scopes') ?? [self::SCOPE_READ];
    $defaultScopes = array_values(array_intersect($defaultScopes, $allowedScopes));
    if (empty($defaultScopes)) {
      $defaultScopes = $allowedScopes;
    }

    // Check for scope in request header (for HTTP transport).
    $request = $this->requestStack->getCurrentRequest();
    if ($request && $config->get('access.trust_scopes_via_header') && $request->headers->has('X-MCP-Scope')) {
      $scopeHeader = $request->headers->get('X-MCP-Scope');
      $requestedScopes = array_values(array_intersect($this->parseScopes($scopeHeader), $allowedScopes));
      if (!empty($requestedScopes)) {
        $this->currentScopes = $requestedScopes;
        return $this->currentScopes;
      }
    }

    // Check for scope in query parameter.
    if ($request && $config->get('access.trust_scopes_via_query') && $request->query->has('mcp_scope')) {
      $scopeParam = $request->query->get('mcp_scope');
      $requestedScopes = array_values(array_intersect($this->parseScopes($scopeParam), $allowedScopes));
      if (!empty($requestedScopes)) {
        $this->currentScopes = $requestedScopes;
        return $this->currentScopes;
      }
    }

    // Check environment variable (for STDIO transport via drush).
    if ($config->get('access.trust_scopes_via_env')) {
      $envScope = getenv('MCP_SCOPE');
      if ($envScope) {
        $requestedScopes = array_values(array_intersect($this->parseScopes($envScope), $allowedScopes));
        if (!empty($requestedScopes)) {
          $this->currentScopes = $requestedScopes;
          return $this->currentScopes;
        }
      }
    }

    $this->currentScopes = $defaultScopes;

    return $this->currentScopes;
  }

  /**
   * Set the current scopes (for testing or programmatic use).
   *
   * @param array $scopes
   *   Array of scope strings.
   */
  public function setScopes(array $scopes): void {
    $this->currentScopes = array_intersect($scopes, self::ALL_SCOPES);
  }

  /**
   * Convenience access check for write/admin tools.
   *
   * Some submodules call this helper to return a normalized access result.
   *
   * @param string $operation
   *   A high-level operation label (e.g., create, update, delete, clear, admin).
   * @param string $entityType
   *   The entity type being modified (used for context only).
   *
   * @return array
   *   Array with keys:
   *   - allowed: bool
   *   - reason: string|null
   *   - code: string|null
   *   - retry_after: int|null
   */
  public function checkWriteAccess(string $operation, string $entityType): array {
    $operationLower = strtolower(trim($operation));

    // Treat explicit "admin" operations as requiring admin scope.
    if ($operationLower === 'admin') {
      if ($this->canAdmin('admin')) {
        return ['allowed' => TRUE, 'reason' => NULL, 'code' => NULL, 'retry_after' => NULL];
      }

      // Mirror getWriteAccessDenied() semantics, but with admin wording.
      if ($this->lastRateLimitError) {
        $error = $this->lastRateLimitError;
        $this->lastRateLimitError = NULL;
        return [
          'allowed' => FALSE,
          'reason' => $error['error'] ?? 'Rate limit exceeded.',
          'code' => $error['code'] ?? ErrorCode::RATE_LIMIT_EXCEEDED,
          'retry_after' => $error['retry_after'] ?? NULL,
        ];
      }

      if ($this->configFactory->get('mcp_tools.settings')->get('access.read_only_mode')) {
        return [
          'allowed' => FALSE,
          'reason' => 'Admin operations are disabled. Site is in read-only mode.',
          'code' => ErrorCode::READ_ONLY_MODE,
          'retry_after' => NULL,
        ];
      }

      return [
        'allowed' => FALSE,
        'reason' => "Admin operations not allowed for this connection. Scope: " . implode(',', $this->getCurrentScopes()),
        'code' => ErrorCode::INSUFFICIENT_SCOPE,
        'retry_after' => NULL,
      ];
    }

    // Map common operations to rate-limit buckets.
    $operationType = match ($operationLower) {
      'delete' => 'delete',
      // For now, treat everything else as a generic write operation.
      default => 'write',
    };

    if ($this->canWrite($operationType)) {
      return ['allowed' => TRUE, 'reason' => NULL, 'code' => NULL, 'retry_after' => NULL];
    }

    $denied = $this->getWriteAccessDenied();
    return [
      'allowed' => FALSE,
      'reason' => $denied['error'] ?? "Write operations are not allowed for {$entityType}.",
      'code' => $denied['code'] ?? ErrorCode::ACCESS_DENIED,
      'retry_after' => $denied['retry_after'] ?? NULL,
    ];
  }

  /**
   * Get access denied response for read operations.
   *
   * @return array
   *   Error response array.
   */
  public function getReadAccessDenied(): array {
    return [
      'success' => FALSE,
      'error' => 'Read operations not allowed for this connection. Scope: ' . implode(',', $this->getCurrentScopes()),
      'code' => ErrorCode::INSUFFICIENT_SCOPE,
    ];
  }

  /**
   * Get access denied response for write operations.
   *
   * @return array
   *   Error response array.
   */
  public function getWriteAccessDenied(): array {
    $config = $this->configFactory->get('mcp_tools.settings');

    // Check for rate limit error first.
    if ($this->lastRateLimitError) {
      $error = $this->lastRateLimitError;
      $this->lastRateLimitError = NULL;
      return [
        'success' => FALSE,
        'error' => $error['error'],
        'code' => $error['code'] ?? ErrorCode::RATE_LIMIT_EXCEEDED,
        'retry_after' => $error['retry_after'] ?? NULL,
      ];
    }

    if ($config->get('access.read_only_mode')) {
      return [
        'success' => FALSE,
        'error' => 'Write operations are disabled. Site is in read-only mode.',
        'code' => ErrorCode::READ_ONLY_MODE,
      ];
    }

    return [
      'success' => FALSE,
      'error' => 'Write operations not allowed for this connection. Scope: ' . implode(',', $this->getCurrentScopes()),
      'code' => ErrorCode::INSUFFICIENT_SCOPE,
    ];
  }

  /**
   * Check if global read-only mode is enabled.
   *
   * @return bool
   *   TRUE if read-only mode is enabled.
   */
  public function isReadOnlyMode(): bool {
    return (bool) $this->configFactory->get('mcp_tools.settings')->get('access.read_only_mode');
  }

  /**
   * Parse scope string into array.
   *
   * @param string $scopeString
   *   Comma-separated scope string.
   *
   * @return array
   *   Array of valid scopes.
   */
  protected function parseScopes(string $scopeString): array {
    $scopes = array_map('trim', explode(',', $scopeString));
    return array_values(array_intersect($scopes, self::ALL_SCOPES));
  }

}
