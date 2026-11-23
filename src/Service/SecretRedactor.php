<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

/**
 * Takes secrets out of data a tool gives back or a log line keeps.
 *
 * One list of what counts as a secret, for every place: get_config had
 * one, the config diff had none, the tool log had none.
 */
final class SecretRedactor {

  /**
   * What a secret's key contains.
   */
  private const SENSITIVE = [
    'password', 'pass', 'secret', 'token', 'key', 'credential', 'api_key',
    'apikey', 'client_secret', 'private', 'salt',
  ];

  /**
   * Keys that contain one of those words and are not secrets.
   */
  private const NOT_SECRET = ['langcode', 'keys', 'key_value', 'primary_key', 'sort_key', 'pass_through'];

  /**
   * The redacted text.
   */
  public const REDACTED = '[REDACTED]';

  /**
   * Whether a key names a secret.
   */
  public static function isSensitiveKey(string|int $key): bool {
    $key = strtolower((string) $key);
    if (in_array($key, self::NOT_SECRET, TRUE)) {
      return FALSE;
    }
    foreach (self::SENSITIVE as $word) {
      if (str_contains($key, $word)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Whether any part of a dotted path names a secret.
   */
  public static function isSensitivePath(string $path): bool {
    foreach (explode('.', $path) as $part) {
      if (self::isSensitiveKey($part)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * The data with every value under a secret's key replaced, at any depth.
   */
  public static function redact(array $data): array {
    foreach ($data as $key => $value) {
      if (self::isSensitiveKey($key)) {
        $data[$key] = self::REDACTED;
      }
      elseif (is_array($value)) {
        $data[$key] = self::redact($value);
      }
    }
    return $data;
  }

}
