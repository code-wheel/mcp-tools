<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Service;

use CodeWheel\McpSecurity\ApiKey\ApiKey;
use CodeWheel\McpSecurity\ApiKey\ApiKeyManager as BaseApiKeyManager;
use CodeWheel\McpSecurity\ApiKey\ApiKeyManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools_remote\Clock\DrupalClock;
use Drupal\mcp_tools_remote\Storage\DrupalStateStorage;

/**
 * Manages API keys for the MCP Tools remote HTTP endpoint.
 *
 * Delegates to code-wheel/mcp-http-security package while maintaining
 * backward compatibility with the existing Drupal API.
 *
 * Keys are stored hashed in State (not exported to config).
 */
final class ApiKeyManager {

  /**
   * The underlying API key manager from the extracted package.
   */
  private readonly ApiKeyManagerInterface $manager;

  public function __construct(
    StateInterface $state,
    PrivateKey $privateKey,
    TimeInterface $time,
  ) {
    $storage = new DrupalStateStorage($state);
    $clock = new DrupalClock($time);
    $pepper = (string) $privateKey->get();

    // Use 'mcp_tools' prefix for backward compatibility with existing keys.
    $this->manager = new BaseApiKeyManager($storage, $clock, $pepper, 'mcp_tools');
  }

  /**
   * Creates a new API key.
   *
   * @param string $label
   *   Human label (for admins).
   * @param string[] $scopes
   *   Allowed scopes for this key (read, write, admin).
   * @param int|null $ttlSeconds
   *   Optional time-to-live in seconds. When provided and > 0, the key will
   *   expire after this many seconds.
   *
   * @return array{key_id: string, api_key: string}
   *   Key ID and the full API key (shown once).
   */
  public function createKey(string $label, array $scopes, ?int $ttlSeconds = NULL): array {
    return $this->manager->createKey($label, $scopes, $ttlSeconds);
  }

  /**
   * Lists keys (redacted).
   *
   * @return array<string, array<string, mixed>>
   *   Key metadata keyed by key ID.
   */
  public function listKeys(): array {
    $keys = $this->manager->listKeys();
    $out = [];

    foreach ($keys as $keyId => $apiKey) {
      $out[$keyId] = $this->apiKeyToArray($apiKey);
    }

    return $out;
  }

  /**
   * Revokes (deletes) a key by ID.
   */
  public function revokeKey(string $keyId): bool {
    return $this->manager->revokeKey($keyId);
  }

  /**
   * Validates an incoming API key.
   *
   * @return array{key_id: string, label: string, scopes: string[]}|null
   *   Key metadata, or NULL if invalid.
   */
  public function validate(string $apiKey): ?array {
    $result = $this->manager->validate($apiKey);
    if ($result === NULL) {
      return NULL;
    }

    return [
      'key_id' => $result->keyId,
      'label' => $result->label,
      'scopes' => $result->scopes,
    ];
  }

  /**
   * Converts an ApiKey object to array format for backward compatibility.
   *
   * @return array<string, mixed>
   */
  private function apiKeyToArray(ApiKey $apiKey): array {
    return [
      'label' => $apiKey->label,
      'scopes' => $apiKey->scopes,
      'created' => $apiKey->created,
      'last_used' => $apiKey->lastUsed,
      'expires' => $apiKey->expires,
    ];
  }

}
