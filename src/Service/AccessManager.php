<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

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
class AccessManager {

  /**
   * Available scopes.
   */
  public const SCOPE_READ = 'read';
  public const SCOPE_WRITE = 'write';
  public const SCOPE_ADMIN = 'admin';

  /**
   * All available scopes.
   */
  public const ALL_SCOPES = [
    self::SCOPE_READ,
    self::SCOPE_WRITE,
    self::SCOPE_ADMIN,
  ];

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

    // Check for scope in request header (for HTTP transport).
    $request = $this->requestStack->getCurrentRequest();
    if ($request && $request->headers->has('X-MCP-Scope')) {
      $scopeHeader = $request->headers->get('X-MCP-Scope');
      $this->currentScopes = $this->parseScopes($scopeHeader);
      return $this->currentScopes;
    }

    // Check for scope in query parameter.
    if ($request && $request->query->has('mcp_scope')) {
      $scopeParam = $request->query->get('mcp_scope');
      $this->currentScopes = $this->parseScopes($scopeParam);
      return $this->currentScopes;
    }

    // Check environment variable (for STDIO transport via drush).
    $envScope = getenv('MCP_SCOPE');
    if ($envScope) {
      $this->currentScopes = $this->parseScopes($envScope);
      return $this->currentScopes;
    }

    // Default scopes from config.
    $config = $this->configFactory->get('mcp_tools.settings');
    $this->currentScopes = $config->get('access.default_scopes') ?? [self::SCOPE_READ, self::SCOPE_WRITE];

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
        'code' => $error['code'] ?? 'RATE_LIMIT_EXCEEDED',
        'retry_after' => $error['retry_after'] ?? NULL,
      ];
    }

    if ($config->get('access.read_only_mode')) {
      return [
        'success' => FALSE,
        'error' => 'Write operations are disabled. Site is in read-only mode.',
        'code' => 'READ_ONLY_MODE',
      ];
    }

    return [
      'success' => FALSE,
      'error' => 'Write operations not allowed for this connection. Scope: ' . implode(',', $this->getCurrentScopes()),
      'code' => 'INSUFFICIENT_SCOPE',
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
    return array_intersect($scopes, self::ALL_SCOPES);
  }

}
