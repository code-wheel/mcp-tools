<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use CodeWheel\McpErrorCodes\ErrorCode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolManager;
use Drupal\tool\Tool\ToolOperation;

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

  /**
   * Scopes fixed for this PHP process by the STDIO server, if any.
   *
   * @var string[]|null
   */
  private static ?array $processScopes = NULL;

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
   * Whether scopes and site modes allow a tool of another provider.
   *
   * MCP Tools' own tools check these in their access check, together with
   * their category permission. A Tool API tool of another module knows
   * nothing of them, so whoever runs it for an MCP client or an AI agent
   * asks here first. The rule is the one MCP Tools' tools follow, by the
   * operation the tool declares: trigger needs the admin scope, write the
   * write scope, anything else the read scope, and writes stop in read-only
   * mode. Config-only mode lets a write through only when every kind of
   * write is allowed, since nothing says what such a tool writes.
   *
   * @param string $operation
   *   The Tool API operation: read, explain, transform, write or trigger.
   *
   * @return bool
   *   TRUE if the tool may run.
   */
  public function allowsForeignToolOperation(string $operation): bool {
    $scope = match ($operation) {
      'trigger' => self::SCOPE_ADMIN,
      'write' => self::SCOPE_WRITE,
      default => self::SCOPE_READ,
    };
    if (!$this->hasScope($scope)) {
      return FALSE;
    }
    if ($scope === self::SCOPE_READ) {
      return TRUE;
    }
    if ($this->isReadOnlyMode()) {
      return FALSE;
    }
    foreach (self::ALL_WRITE_KINDS as $kind) {
      if (!$this->isWriteKindAllowed($kind)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Whether this session could call a tool at all.
   *
   * Asked without making the tool, so it is cheap enough for a list. For a
   * tool of MCP Tools it is the tool's own access rule. For a tool of
   * another provider it is the rule the server applies at the door (scopes
   * and modes); what that tool checks itself is not known here, so it is
   * never hidden for a reason of its own.
   *
   * @param \Drupal\tool\Tool\ToolDefinition $definition
   *   The tool.
   *
   * @return bool
   *   FALSE when a call would certainly be refused.
   */
  public function couldCall(ToolDefinition $definition): bool {
    $operation = $definition->getOperation() ?? ToolOperation::Transform;
    if (McpToolsToolBase::owns($definition)) {
      return $definition->getClass()::accessFor($operation, $this->currentUser, $this)->isAllowed();
    }
    // A permission the tool declares is checked by Tool API before the tool
    // is even made, so a user without it is certain to be refused.
    if (!ToolManager::checkPermission($definition, $this->currentUser)->isAllowed()) {
      return FALSE;
    }
    return $this->allowsForeignToolOperation($operation->value);
  }

  /**
   * Whether a tool belongs on this session's tool list.
   *
   * The list is what the session may call, unless the site asks for the
   * whole list (access.list_all_tools). A tool that is not listed is still
   * refused by name with "Access denied", as before.
   *
   * @param \Drupal\tool\Tool\ToolDefinition $definition
   *   The tool.
   *
   * @return bool
   *   TRUE if the tool is listed.
   */
  public function listsTool(ToolDefinition $definition): bool {
    if ($this->configFactory->get('mcp_tools.settings')->get('access.list_all_tools')) {
      return TRUE;
    }
    return $this->couldCall($definition);
  }

  /**
   * Whether the site insists on a confirmation for destructive tools.
   *
   * It decides what happens where nobody can be asked: a client without
   * elicitation, Drupal AI, MCP Server with mcp_tools_policy. With this on,
   * a tool that declares itself destructive is refused there, and so are
   * the two calls of MCP Tools' own that ask first. With it off the call
   * runs, with a warning in the audit log. On is the default of a site
   * installed with 1.0.0-beta22 or later. A site from before has no value,
   * which is off.
   *
   * @return bool
   *   TRUE if a destructive tool may only run after a confirmation.
   */
  public function destructiveToolsNeedConfirmation(): bool {
    return (bool) $this->configFactory->get('mcp_tools.settings')->get('access.confirm_destructive_tools');
  }

  /**
   * Check if a write kind is allowed under config-only policy.
   *
   * When config-only mode is disabled, all write kinds are allowed
   * (subject to scopes/read-only mode and tool permissions).
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
   * @param int $items
   *   How many items the call writes: each one counts.
   *
   * @return bool
   *   TRUE if write operations are allowed.
   */
  public function canWrite(string $operationType = 'write', int $items = 1): bool {
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
      $rateCheck = $this->rateLimiter->checkLimit($operationType, $items);
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
   * @param int $items
   *   How many items the call writes: each one counts.
   *
   * @return bool
   *   TRUE if admin operations are allowed.
   */
  public function canAdmin(string $operationType = 'structure', int $items = 1): bool {
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
      $rateCheck = $this->rateLimiter->checkLimit($operationType, $items);
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
    if (!empty(self::$processScopes)) {
      $this->currentScopes = self::$processScopes;
      return $this->currentScopes;
    }

    $config = $this->configFactory->get('mcp_tools.settings');

    // Maximum allowed scopes are always enforced.
    $allowedScopes = $this->allowedScopes();

    // Default scopes (used when no trusted override is present).
    $defaultScopes = $config->get('access.default_scopes') ?? [self::SCOPE_READ];
    $defaultScopes = array_values(array_intersect($defaultScopes, $allowedScopes));
    // None, or none that is allowed, is not a wish for all of them.
    if (empty($defaultScopes)) {
      $defaultScopes = array_values(array_intersect([self::SCOPE_READ], $allowedScopes));
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
   * The most any connection gets on this site (access.allowed_scopes).
   *
   * @return string[]
   *   The allowed scopes. Never empty: read is always allowed, which
   *   prevents an accidental lockout.
   */
  private function allowedScopes(): array {
    $config = $this->configFactory->get('mcp_tools.settings');
    $allowedScopes = $config->get('access.allowed_scopes')
      ?? $config->get('access.default_scopes')
      ?? [self::SCOPE_READ];
    $allowedScopes = array_values(array_intersect($allowedScopes, self::ALL_SCOPES));
    return $allowedScopes === [] ? [self::SCOPE_READ] : $allowedScopes;
  }

  /**
   * Takes out of a list of scopes what the site does not allow.
   *
   * The setting access.allowed_scopes is the most any connection gets,
   * whoever asks for more: an API key, a header, the environment, and since
   * 1.0.0-beta21 also a server profile and --scope on the command line of
   * the STDIO server. Those two were used as given, so the documented
   * "--server=development" ran with the admin scope on a site that had
   * never allowed it.
   *
   * @param string[] $scopes
   *   The scopes that were asked for.
   *
   * @return array{0: string[], 1: string[]}
   *   The scopes that stay, and the ones that were taken out. What stays is
   *   never empty: a connection left with nothing can read.
   */
  public function limitToAllowed(array $scopes): array {
    $scopes = array_values(array_intersect($scopes, self::ALL_SCOPES));
    $kept = array_values(array_intersect($scopes, $this->allowedScopes()));
    $dropped = array_values(array_diff($scopes, $kept));
    return [$kept === [] ? [self::SCOPE_READ] : $kept, $dropped];
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
   * Fixes the scopes for the life of this PHP process.
   *
   * For the STDIO server only. It is one process for one client session, and
   * a tool that clears all caches rebuilds the container inside it. That
   * replaces this service, and the scopes set on the old instance are gone:
   * the session fell back to the default scopes and refused every later
   * write until it was restarted. Scopes set here outlive the rebuild.
   *
   * Never call this in a web request: PHP-FPM reuses a process for many
   * requests, and the next one would inherit the scopes.
   *
   * @param string[]|null $scopes
   *   The scopes, or NULL to clear them.
   */
  public static function setProcessScopes(?array $scopes): void {
    self::$processScopes = $scopes === NULL
      ? NULL
      : array_values(array_intersect($scopes, self::ALL_SCOPES));
  }

  /**
   * Convenience access check for write/admin tools.
   *
   * Some submodules call this helper to return a normalized access result.
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
