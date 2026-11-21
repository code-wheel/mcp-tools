<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolOperation;

/**
 * Service for logging MCP write operations.
 *
 * Shared by all MCP write submodules.
 */
class AuditLogger implements AuditLoggerInterface {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected AccountProxyInterface $currentUser,
    LoggerChannelFactoryInterface $loggerFactory,
    protected AccessManager $accessManager,
    protected RequestStack $requestStack,
    protected McpToolCallContext $toolCallContext,
    protected RateLimiter $rateLimiter,
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
    $enabled = $config->get('access.audit_logging');
    if ($enabled === NULL) {
      $enabled = TRUE;
    }
    if (!$enabled) {
      return;
    }

    // Drush has a request too, so a request alone does not mean HTTP. Our
    // endpoint marks its request with the key that was used.
    $request = $this->requestStack->getCurrentRequest();
    $viaEndpoint = $request && $request->attributes->has('mcp_tools.client_id');
    $transport = ($viaEndpoint || PHP_SAPI !== 'cli') ? 'http' : 'cli';

    $client = '';
    if ($request) {
      $client = (string) $request->attributes->get('mcp_tools.client_id', '');
    }
    if ($client === '') {
      $rateStatus = $this->rateLimiter->getStatus();
      $client = (string) ($rateStatus['client_id'] ?? '-');
    }
    if ($client === '') {
      $client = '-';
    }

    $scopes = $this->accessManager->getCurrentScopes();
    $scopesText = $scopes ? implode(',', $scopes) : '-';

    $correlationId = $this->toolCallContext->getCorrelationId() ?? '-';

    $context = [
      '@operation' => $operation,
      '@entity_type' => $entityType,
      '@entity_id' => $entityId,
      '@user' => $this->currentUser->getAccountName() ?: 'anonymous',
      '@uid' => $this->currentUser->id(),
      '@cid' => $correlationId,
      '@transport' => $transport,
      '@client' => $client,
      '@scopes' => $scopesText,
    ];

    $message = 'MCP[@cid] (@transport, client: @client, scopes: @scopes): @operation on @entity_type "@entity_id" by @user (uid: @uid)';

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
   * Logs a call to a write or trigger tool of another provider.
   *
   * MCP Tools' own tools log from inside their services, with the thing
   * they changed. For a Tool API tool of another module, whoever ran it logs
   * here: which tool, whose, the arguments, and whether it worked. A read
   * tool and a tool of MCP Tools itself leave no line.
   *
   * @param \Drupal\tool\Tool\ToolDefinition $definition
   *   The tool that ran.
   * @param array<string, mixed> $arguments
   *   The arguments it ran with.
   * @param bool $success
   *   Whether it reported success.
   */
  public function logForeignTool(ToolDefinition $definition, array $arguments, bool $success): void {
    if (McpToolsToolBase::owns($definition)) {
      return;
    }
    $provider = (string) $definition->getProvider();
    $operation = $definition->getOperation() ?? ToolOperation::Transform;
    if (!in_array($operation, [ToolOperation::Write, ToolOperation::Trigger], TRUE)) {
      return;
    }

    $this->log('run_' . $operation->value . '_tool', 'tool', (string) $definition->id(), [
      'provider' => $provider,
      'declared_destructive' => $definition->get('destructive') === TRUE,
      'arguments' => $arguments,
    ], $success);
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
      // Array keys may be integers (lists); strtolower() rejects non-strings
      // under strict_types.
      $lowerKey = strtolower((string) $key);
      $isSensitive = FALSE;
      foreach ($sensitiveKeys as $sensitiveKey) {
        if (str_contains($lowerKey, $sensitiveKey)) {
          $details[$key] = '[REDACTED]';
          $isSensitive = TRUE;
          break;
        }
      }

      // Only recurse into arrays if the key itself wasn't sensitive.
      if (!$isSensitive && is_array($value)) {
        $details[$key] = $this->sanitizeDetails($value);
      }
    }

    return $details;
  }

}
