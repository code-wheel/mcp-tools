<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\State\StateInterface;

/**
 * Manages API keys for the MCP Tools remote HTTP endpoint.
 *
 * Keys are stored hashed in State (not exported to config).
 */
final class ApiKeyManager {

  private const STATE_KEY = 'mcp_tools_remote.api_keys';
  private const KEY_PREFIX = 'mcp_tools';

  public function __construct(
    private readonly StateInterface $state,
    private readonly PrivateKey $privateKey,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Creates a new API key.
   *
   * @param string $label
   *   Human label (for admins).
   * @param string[] $scopes
   *   Allowed scopes for this key (read, write, admin).
   *
   * @return array{key_id: string, api_key: string}
   *   Key ID and the full API key (shown once).
   */
  public function createKey(string $label, array $scopes): array {
    $label = trim($label);
    if ($label === '') {
      $label = 'Unnamed key';
    }

    $keyId = $this->generateKeyId();
    $secret = $this->generateSecret();

    $record = [
      'label' => $label,
      'scopes' => array_values(array_unique(array_filter(array_map('strval', $scopes)))),
      'hash' => $this->hashSecret($secret),
      'created' => $this->time->getRequestTime(),
      'last_used' => NULL,
    ];

    $all = $this->state->get(self::STATE_KEY, []);
    $all[$keyId] = $record;
    $this->state->set(self::STATE_KEY, $all);

    return [
      'key_id' => $keyId,
      'api_key' => self::KEY_PREFIX . '.' . $keyId . '.' . $secret,
    ];
  }

  /**
   * Lists keys (redacted).
   *
   * @return array<string, array<string, mixed>>
   *   Key metadata keyed by key ID.
   */
  public function listKeys(): array {
    $all = $this->state->get(self::STATE_KEY, []);
    $out = [];

    foreach ($all as $keyId => $record) {
      if (!is_array($record)) {
        continue;
      }
      $out[$keyId] = [
        'label' => (string) ($record['label'] ?? ''),
        'scopes' => array_values(array_filter($record['scopes'] ?? [])),
        'created' => $record['created'] ?? NULL,
        'last_used' => $record['last_used'] ?? NULL,
      ];
    }

    ksort($out);
    return $out;
  }

  /**
   * Revokes (deletes) a key by ID.
   */
  public function revokeKey(string $keyId): bool {
    $all = $this->state->get(self::STATE_KEY, []);
    if (!isset($all[$keyId])) {
      return FALSE;
    }
    unset($all[$keyId]);
    $this->state->set(self::STATE_KEY, $all);
    return TRUE;
  }

  /**
   * Validates an incoming API key.
   *
   * @return array{key_id: string, label: string, scopes: string[]}|null
   *   Key metadata, or NULL if invalid.
   */
  public function validate(string $apiKey): ?array {
    $apiKey = trim($apiKey);
    if ($apiKey === '') {
      return NULL;
    }

    $parts = explode('.', $apiKey, 3);
    if (count($parts) !== 3) {
      return NULL;
    }

    [$prefix, $keyId, $secret] = $parts;
    if ($prefix !== self::KEY_PREFIX || $keyId === '' || $secret === '') {
      return NULL;
    }

    $all = $this->state->get(self::STATE_KEY, []);
    $record = $all[$keyId] ?? NULL;
    if (!is_array($record) || empty($record['hash'])) {
      return NULL;
    }

    $expected = (string) $record['hash'];
    $actual = $this->hashSecret($secret);

    if (!hash_equals($expected, $actual)) {
      return NULL;
    }

    // Update last-used timestamp.
    $record['last_used'] = $this->time->getRequestTime();
    $all[$keyId] = $record;
    $this->state->set(self::STATE_KEY, $all);

    return [
      'key_id' => $keyId,
      'label' => (string) ($record['label'] ?? ''),
      'scopes' => array_values(array_filter($record['scopes'] ?? [])),
    ];
  }

  private function generateKeyId(): string {
    return substr(bin2hex(random_bytes(8)), 0, 12);
  }

  private function generateSecret(): string {
    $raw = random_bytes(32);
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
  }

  private function hashSecret(string $secret): string {
    $pepper = (string) $this->privateKey->get();
    return hash('sha256', $pepper . ':' . $secret);
  }

}

