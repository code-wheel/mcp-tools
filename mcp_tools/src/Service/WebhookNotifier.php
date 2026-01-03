<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for sending webhook notifications on MCP operations.
 *
 * Allows external systems to be notified when MCP makes changes,
 * useful for audit logging, Slack notifications, etc.
 */
class WebhookNotifier {

  /**
   * Operation types.
   */
  public const OP_CREATE = 'create';
  public const OP_UPDATE = 'update';
  public const OP_DELETE = 'delete';
  public const OP_STRUCTURE = 'structure';

  /**
   * Blocked hosts for SSRF protection.
   *
   * These hosts are always blocked regardless of allowed_hosts config.
   */
  protected const BLOCKED_HOSTS = [
    'localhost',
    '127.0.0.1',
    '0.0.0.0',
    '::1',
    '169.254.169.254',  // AWS/GCP metadata service
    'metadata.google.internal',  // GCP metadata
    '100.100.100.200',  // Alibaba Cloud metadata
  ];

  /**
   * Blocked IP ranges (private networks).
   */
  protected const BLOCKED_IP_RANGES = [
    '10.0.0.0/8',
    '172.16.0.0/12',
    '192.168.0.0/16',
    '169.254.0.0/16',  // Link-local
    'fc00::/7',  // IPv6 private
    'fe80::/10',  // IPv6 link-local
  ];

  /**
   * Queue of pending notifications (for batch sending).
   *
   * @var array
   */
  protected array $queue = [];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ClientInterface $httpClient,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Notify about an MCP operation.
   *
   * @param string $operation
   *   The operation type (create, update, delete, structure).
   * @param string $entityType
   *   The entity type affected (e.g., 'node', 'user', 'field').
   * @param string|int $entityId
   *   The entity ID or identifier.
   * @param string $label
   *   Human-readable label for the entity.
   * @param array $details
   *   Additional details about the operation.
   *
   * @return bool
   *   TRUE if notification was sent successfully.
   */
  public function notify(
    string $operation,
    string $entityType,
    string|int $entityId,
    string $label,
    array $details = []
  ): bool {
    $config = $this->configFactory->get('mcp_tools.settings');

    // Check if webhooks are enabled.
    if (!$config->get('webhooks.enabled')) {
      return TRUE;
    }

    $webhookUrl = $config->get('webhooks.url');
    if (empty($webhookUrl)) {
      return TRUE;
    }

    // Build the payload.
    $payload = $this->buildPayload($operation, $entityType, $entityId, $label, $details);

    // Check if batching is enabled.
    if ($config->get('webhooks.batch_notifications')) {
      $this->queue[] = $payload;
      return TRUE;
    }

    // Send immediately.
    return $this->sendPayload([$payload]);
  }

  /**
   * Send queued notifications.
   *
   * Called on shutdown or manually.
   *
   * @return bool
   *   TRUE if all notifications sent successfully.
   */
  public function flush(): bool {
    if (empty($this->queue)) {
      return TRUE;
    }

    $result = $this->sendPayload($this->queue);
    $this->queue = [];
    return $result;
  }

  /**
   * Build the notification payload.
   *
   * @param string $operation
   *   Operation type.
   * @param string $entityType
   *   Entity type.
   * @param string|int $entityId
   *   Entity ID.
   * @param string $label
   *   Entity label.
   * @param array $details
   *   Additional details.
   *
   * @return array
   *   Notification payload.
   */
  protected function buildPayload(
    string $operation,
    string $entityType,
    string|int $entityId,
    string $label,
    array $details
  ): array {
    return [
      'timestamp' => date('c'),
      'source' => 'mcp_tools',
      'operation' => $operation,
      'entity_type' => $entityType,
      'entity_id' => $entityId,
      'label' => $label,
      'user' => [
        'id' => $this->currentUser->id(),
        'name' => $this->currentUser->getAccountName(),
      ],
      'details' => $this->sanitizeDetails($details),
    ];
  }

  /**
   * Sanitize details to remove sensitive information.
   *
   * @param array $details
   *   Details array.
   *
   * @return array
   *   Sanitized details.
   */
  protected function sanitizeDetails(array $details): array {
    $sensitiveKeys = ['password', 'pass', 'secret', 'token', 'key', 'api_key', 'apikey'];

    array_walk_recursive($details, function (&$value, $key) use ($sensitiveKeys) {
      foreach ($sensitiveKeys as $sensitiveKey) {
        if (stripos($key, $sensitiveKey) !== FALSE) {
          $value = '[REDACTED]';
          return;
        }
      }
    });

    return $details;
  }

  /**
   * Send payload to webhook URL.
   *
   * @param array $events
   *   Array of event payloads.
   *
   * @return bool
   *   TRUE if sent successfully.
   */
  protected function sendPayload(array $events): bool {
    $config = $this->configFactory->get('mcp_tools.settings');
    $webhookUrl = $config->get('webhooks.url');
    $secret = $config->get('webhooks.secret');

    if (empty($webhookUrl)) {
      return FALSE;
    }

    // SECURITY: Validate webhook URL against SSRF attacks.
    $urlValidation = $this->validateWebhookUrl($webhookUrl);
    if (!$urlValidation['valid']) {
      $this->loggerFactory->get('mcp_tools')->error(
        'Webhook URL rejected for security: @reason',
        ['@reason' => $urlValidation['reason']]
      );
      return FALSE;
    }

    $body = Json::encode([
      'events' => $events,
      'count' => count($events),
    ]);

    $headers = [
      'Content-Type' => 'application/json',
      'User-Agent' => 'MCP-Tools/1.0',
    ];

    // Add HMAC signature if secret is configured.
    if (!empty($secret)) {
      $headers['X-MCP-Signature'] = 'sha256=' . hash_hmac('sha256', $body, $secret);
    }

    try {
      $response = $this->httpClient->request('POST', $webhookUrl, [
        'headers' => $headers,
        'body' => $body,
        'timeout' => $config->get('webhooks.timeout') ?? 5,
        'http_errors' => FALSE,
        // Prevent redirects to internal hosts.
        'allow_redirects' => [
          'max' => 3,
          'on_redirect' => function ($request, $response, $uri) {
            $redirectValidation = $this->validateWebhookUrl((string) $uri);
            if (!$redirectValidation['valid']) {
              throw new \RuntimeException('Redirect to blocked URL: ' . $redirectValidation['reason']);
            }
          },
        ],
      ]);

      $statusCode = $response->getStatusCode();
      if ($statusCode >= 200 && $statusCode < 300) {
        return TRUE;
      }

      $this->loggerFactory->get('mcp_tools')->warning(
        'Webhook notification failed with status @status',
        ['@status' => $statusCode]
      );
      return FALSE;

    }
    catch (GuzzleException $e) {
      $this->loggerFactory->get('mcp_tools')->error(
        'Webhook notification failed: @message',
        ['@message' => $e->getMessage()]
      );
      return FALSE;
    }
  }

  /**
   * Validate webhook URL for SSRF protection.
   *
   * @param string $url
   *   The URL to validate.
   *
   * @return array
   *   ['valid' => bool, 'reason' => string|null]
   */
  protected function validateWebhookUrl(string $url): array {
    $parsed = parse_url($url);

    if (!$parsed || empty($parsed['host'])) {
      return ['valid' => FALSE, 'reason' => 'Invalid URL format'];
    }

    // Only allow http and https schemes.
    $scheme = strtolower($parsed['scheme'] ?? '');
    if (!in_array($scheme, ['http', 'https'], TRUE)) {
      return ['valid' => FALSE, 'reason' => 'Only http/https schemes allowed'];
    }

    $host = strtolower($parsed['host']);

    // Check against blocked hosts.
    foreach (self::BLOCKED_HOSTS as $blockedHost) {
      if ($host === strtolower($blockedHost)) {
        return ['valid' => FALSE, 'reason' => "Host '$host' is blocked (internal/metadata service)"];
      }
    }

    // Resolve hostname to IP and check against blocked ranges.
    $ip = gethostbyname($host);
    if ($ip !== $host) {
      // Successfully resolved to IP.
      if ($this->isPrivateIp($ip)) {
        return ['valid' => FALSE, 'reason' => "Host '$host' resolves to private IP '$ip'"];
      }
    }

    // Check if it's a direct IP address.
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      if ($this->isPrivateIp($host)) {
        return ['valid' => FALSE, 'reason' => "IP address '$host' is in private range"];
      }
    }

    // Check against allowed hosts if configured.
    $config = $this->configFactory->get('mcp_tools.settings');
    $allowedHosts = $config->get('webhooks.allowed_hosts') ?? [];

    if (!empty($allowedHosts)) {
      $isAllowed = FALSE;
      foreach ($allowedHosts as $allowedHost) {
        // Support wildcard patterns (e.g., *.example.com).
        $pattern = str_replace(['.', '*'], ['\.', '.*'], strtolower($allowedHost));
        if (preg_match('/^' . $pattern . '$/i', $host)) {
          $isAllowed = TRUE;
          break;
        }
      }
      if (!$isAllowed) {
        return ['valid' => FALSE, 'reason' => "Host '$host' not in allowed hosts list"];
      }
    }

    return ['valid' => TRUE, 'reason' => NULL];
  }

  /**
   * Check if an IP address is in a private/reserved range.
   *
   * @param string $ip
   *   The IP address to check.
   *
   * @return bool
   *   TRUE if the IP is private/reserved.
   */
  protected function isPrivateIp(string $ip): bool {
    // Use PHP's built-in filter for comprehensive private IP detection.
    return filter_var(
      $ip,
      FILTER_VALIDATE_IP,
      FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) === FALSE;
  }

  /**
   * Get pending notification count.
   *
   * @return int
   *   Number of queued notifications.
   */
  public function getPendingCount(): int {
    return count($this->queue);
  }

}
