<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for logging MCP write operations.
 *
 * Shared by all MCP write submodules.
 */
class AuditLogger {

  protected LoggerInterface $logger;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected AccountProxyInterface $currentUser,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('mcp_tools');
  }

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
  public function log(string $operation, string $entityType, string $entityId, array $details = [], bool $success = TRUE): void {
    $config = $this->configFactory->get('mcp_tools.settings');

    // Check if audit logging is enabled (default TRUE for write operations).
    if (!$config->get('audit_logging') ?? TRUE) {
      return;
    }

    $context = [
      '@operation' => $operation,
      '@entity_type' => $entityType,
      '@entity_id' => $entityId,
      '@user' => $this->currentUser->getAccountName() ?: 'anonymous',
      '@uid' => $this->currentUser->id(),
    ];

    $message = 'MCP: @operation on @entity_type "@entity_id" by @user (uid: @uid)';

    if (!empty($details)) {
      $safeDetails = $this->sanitizeDetails($details);
      $message .= ' | Details: @details';
      $context['@details'] = json_encode($safeDetails, JSON_UNESCAPED_SLASHES);
    }

    if ($success) {
      $this->logger->notice($message, $context);
    }
    else {
      $this->logger->error($message, $context);
    }
  }

  /**
   * Log a successful operation.
   */
  public function logSuccess(string $operation, string $entityType, string $entityId, array $details = []): void {
    $this->log($operation, $entityType, $entityId, $details, TRUE);
  }

  /**
   * Log a failed operation.
   */
  public function logFailure(string $operation, string $entityType, string $entityId, array $details = []): void {
    $this->log($operation, $entityType, $entityId, $details, FALSE);
  }

  /**
   * Sanitize details to remove sensitive information.
   */
  protected function sanitizeDetails(array $details): array {
    $sensitiveKeys = ['password', 'pass', 'secret', 'token', 'key', 'credentials', 'api_key'];

    foreach ($details as $key => $value) {
      $lowerKey = strtolower($key);
      foreach ($sensitiveKeys as $sensitiveKey) {
        if (str_contains($lowerKey, $sensitiveKey)) {
          $details[$key] = '[REDACTED]';
          break;
        }
      }

      if (is_array($value)) {
        $details[$key] = $this->sanitizeDetails($value);
      }
    }

    return $details;
  }

}
