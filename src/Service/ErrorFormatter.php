<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use CodeWheel\McpErrorCodes\ErrorCode;

/**
 * Service for formatting consistent, user-friendly error messages.
 *
 * Provides standardized error responses for common MCP Tools scenarios.
 *
 * @deprecated in mcp_tools:1.0.0 and is removed from mcp_tools:2.0.0.
 *   Use CodeWheel\McpErrorCodes\ErrorCode instead.
 *
 * phpcs:ignore Drupal.Commenting.Deprecated.DeprecatedWrongSeeUrlFormat
 * @see https://packagist.org/packages/code-wheel/mcp-error-codes
 */
class ErrorFormatter {

  /**
   * Error codes for common scenarios.
   *
   * @deprecated in mcp_tools:1.0.0 and is removed from mcp_tools:2.0.0.
   *   Use CodeWheel\McpErrorCodes\ErrorCode constants instead.
   *
   * phpcs:ignore Drupal.Commenting.Deprecated.DeprecatedWrongSeeUrlFormat
   * @see https://packagist.org/packages/code-wheel/mcp-error-codes
   */
  public const ERROR_NOT_FOUND = ErrorCode::NOT_FOUND;
  public const ERROR_ALREADY_EXISTS = ErrorCode::ALREADY_EXISTS;
  public const ERROR_VALIDATION = ErrorCode::VALIDATION_ERROR;
  public const ERROR_PERMISSION = ErrorCode::ACCESS_DENIED;
  public const ERROR_PROTECTED = ErrorCode::ENTITY_PROTECTED;
  public const ERROR_IN_USE = ErrorCode::ENTITY_IN_USE;
  public const ERROR_DEPENDENCY = ErrorCode::MISSING_DEPENDENCY;
  public const ERROR_RATE_LIMIT = ErrorCode::RATE_LIMIT_EXCEEDED;
  public const ERROR_READ_ONLY = ErrorCode::READ_ONLY_MODE;
  public const ERROR_SCOPE = ErrorCode::INSUFFICIENT_SCOPE;
  public const ERROR_INTERNAL = ErrorCode::INTERNAL_ERROR;

  /**
   * Create a "not found" error response.
   *
   * @param string $entityType
   *   The entity type (e.g., 'content type', 'user', 'vocabulary').
   * @param string $identifier
   *   The identifier that was not found.
   * @param string|null $suggestion
   *   Optional suggestion for the user.
   *
   * @return array
   *   Formatted error response.
   */
  public function notFound(string $entityType, string $identifier, ?string $suggestion = NULL): array {
    $message = "The $entityType '$identifier' was not found.";
    if ($suggestion) {
      $message .= " $suggestion";
    }

    return $this->error($message, self::ERROR_NOT_FOUND, [
      'entity_type' => $entityType,
      'identifier' => $identifier,
    ]);
  }

  /**
   * Create an "already exists" error response.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $identifier
   *   The identifier that already exists.
   *
   * @return array
   *   Formatted error response.
   */
  public function alreadyExists(string $entityType, string $identifier): array {
    return $this->error(
      "A $entityType with ID '$identifier' already exists. Use a different ID or update the existing one.",
      self::ERROR_ALREADY_EXISTS,
      [
        'entity_type' => $entityType,
        'identifier' => $identifier,
      ]
    );
  }

  /**
   * Create a validation error response.
   *
   * @param string $field
   *   The field that failed validation.
   * @param string $reason
   *   Why validation failed.
   * @param mixed $value
   *   The invalid value (optional, will be sanitized).
   *
   * @return array
   *   Formatted error response.
   */
  public function validation(string $field, string $reason, mixed $value = NULL): array {
    $message = "Invalid value for '$field': $reason";

    $details = ['field' => $field, 'reason' => $reason];
    if ($value !== NULL && !$this->isSensitive($field)) {
      $details['value'] = $value;
    }

    return $this->error($message, self::ERROR_VALIDATION, $details);
  }

  /**
   * Create a permission denied error response.
   *
   * @param string $operation
   *   The operation that was denied.
   * @param string|null $permission
   *   The required permission (optional).
   *
   * @return array
   *   Formatted error response.
   */
  public function permissionDenied(string $operation, ?string $permission = NULL): array {
    $message = "Permission denied for operation: $operation.";
    if ($permission) {
      $message .= " Required permission: $permission";
    }

    return $this->error($message, self::ERROR_PERMISSION, [
      'operation' => $operation,
      'required_permission' => $permission,
    ]);
  }

  /**
   * Create a protected entity error response.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $identifier
   *   The entity identifier.
   * @param string $reason
   *   Why the entity is protected.
   *
   * @return array
   *   Formatted error response.
   */
  public function protectedEntity(string $entityType, string $identifier, string $reason): array {
    return $this->error(
      "The $entityType '$identifier' is protected and cannot be modified. $reason",
      self::ERROR_PROTECTED,
      [
        'entity_type' => $entityType,
        'identifier' => $identifier,
        'reason' => $reason,
      ]
    );
  }

  /**
   * Create an "entity in use" error response.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $identifier
   *   The entity identifier.
   * @param int $usageCount
   *   Number of places the entity is used.
   * @param bool $forceAvailable
   *   Whether force=true can override.
   *
   * @return array
   *   Formatted error response.
   */
  public function entityInUse(string $entityType, string $identifier, int $usageCount, bool $forceAvailable = TRUE): array {
    $message = "Cannot delete $entityType '$identifier': it is used in $usageCount places.";
    if ($forceAvailable) {
      $message .= " Use force=true to delete anyway.";
    }

    return $this->error($message, self::ERROR_IN_USE, [
      'entity_type' => $entityType,
      'identifier' => $identifier,
      'usage_count' => $usageCount,
      'force_available' => $forceAvailable,
    ]);
  }

  /**
   * Create a missing dependency error response.
   *
   * @param string $dependency
   *   The missing dependency (module, service, etc.).
   * @param string $requiredFor
   *   What the dependency is required for.
   *
   * @return array
   *   Formatted error response.
   */
  public function missingDependency(string $dependency, string $requiredFor): array {
    return $this->error(
      "Missing dependency: '$dependency' is required for $requiredFor. Please enable it first.",
      self::ERROR_DEPENDENCY,
      [
        'dependency' => $dependency,
        'required_for' => $requiredFor,
      ]
    );
  }

  /**
   * Create a rate limit error response.
   *
   * @param string $limitType
   *   The type of limit exceeded.
   * @param int $retryAfter
   *   Seconds until retry is allowed.
   *
   * @return array
   *   Formatted error response.
   */
  public function rateLimitExceeded(string $limitType, int $retryAfter): array {
    return $this->error(
      "Rate limit exceeded for $limitType operations. Try again in $retryAfter seconds.",
      self::ERROR_RATE_LIMIT,
      [
        'limit_type' => $limitType,
        'retry_after' => $retryAfter,
      ]
    );
  }

  /**
   * Create a read-only mode error response.
   *
   * @return array
   *   Formatted error response.
   */
  public function readOnlyMode(): array {
    return $this->error(
      "Write operations are disabled. The site is in read-only mode. Configure at /admin/config/services/mcp-tools.",
      self::ERROR_READ_ONLY
    );
  }

  /**
   * Create an insufficient scope error response.
   *
   * @param string $requiredScope
   *   The required scope.
   * @param array $currentScopes
   *   The current scopes.
   *
   * @return array
   *   Formatted error response.
   */
  public function insufficientScope(string $requiredScope, array $currentScopes): array {
    return $this->error(
      "Insufficient scope for this operation. Required: '$requiredScope'. Current scopes: " . implode(', ', $currentScopes) . ".",
      self::ERROR_SCOPE,
      [
        'required_scope' => $requiredScope,
        'current_scopes' => $currentScopes,
      ]
    );
  }

  /**
   * Create a success response.
   *
   * @param string $message
   *   Success message.
   * @param array $data
   *   Additional data to include.
   *
   * @return array
   *   Formatted success response.
   */
  public function success(string $message, array $data = []): array {
    return [
      'success' => TRUE,
      'message' => $message,
    ] + $data;
  }

  /**
   * Create a generic error response.
   *
   * @param string $message
   *   Error message.
   * @param string $code
   *   Error code.
   * @param array $details
   *   Additional details.
   *
   * @return array
   *   Formatted error response.
   */
  public function error(string $message, string $code = self::ERROR_INTERNAL, array $details = []): array {
    $response = [
      'success' => FALSE,
      'error' => $message,
      'code' => $code,
    ];

    if (!empty($details)) {
      $response['details'] = $details;
    }

    return $response;
  }

  /**
   * Wrap an exception as an error response.
   *
   * @param \Throwable $e
   *   The exception.
   * @param string $context
   *   Context about what operation was being performed.
   *
   * @return array
   *   Formatted error response.
   */
  public function fromException(\Throwable $e, string $context = ''): array {
    $message = $context ? "$context: {$e->getMessage()}" : $e->getMessage();

    return $this->error($message, self::ERROR_INTERNAL, [
      'exception' => get_class($e),
    ]);
  }

  /**
   * Check if a field name indicates sensitive data.
   *
   * @param string $field
   *   The field name.
   *
   * @return bool
   *   TRUE if the field is sensitive.
   */
  protected function isSensitive(string $field): bool {
    $sensitivePatterns = ['password', 'pass', 'secret', 'token', 'key', 'api_key', 'credential'];
    $fieldLower = strtolower($field);

    foreach ($sensitivePatterns as $pattern) {
      if (str_contains($fieldLower, $pattern)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
