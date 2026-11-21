<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Policy;

/**
 * A call the site's rules refused before the tool ran.
 *
 * A refusal is an answer, not an exception: the caller shows the message,
 * and for a limit it knows how many seconds to wait.
 */
final class ToolRefusal {

  /**
   * This session may not run the tool: its scopes, or a site mode.
   */
  public const ACCESS = 'access';

  /**
   * A write limit is used up.
   */
  public const LIMIT = 'limit';

  private function __construct(
    public readonly string $reason,
    public readonly string $message,
    public readonly string $code,
    public readonly int $retryAfter,
  ) {}

  /**
   * Refused for what the session may do.
   */
  public static function access(): self {
    return new self(self::ACCESS, 'Access denied.', 'ACCESS_DENIED', 0);
  }

  /**
   * Refused by a write limit.
   *
   * @param array<string, mixed> $check
   *   What RateLimiter::checkLimit() returned.
   */
  public static function limit(array $check): self {
    return new self(
      self::LIMIT,
      (string) ($check['error'] ?? 'Rate limit exceeded.'),
      (string) ($check['code'] ?? 'RATE_LIMIT_EXCEEDED'),
      (int) ($check['retry_after'] ?? 0),
    );
  }

}
