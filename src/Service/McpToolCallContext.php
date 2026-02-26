<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

/**
 * Tracks whether an MCP tool call is currently executing.
 *
 * This is used to scope side effects (like config-change tracking) to MCP
 * invocations only, without affecting normal Drupal admin actions.
 */
class McpToolCallContext {

  /**
   * The depth.
   *
   * @var int
   */
  private int $depth = 0;

  /**
   * The correlation id.
   *
   * @var string|null
   */
  private ?string $correlationId = NULL;

  /**
   * Mark the beginning of a tool call.
   */
  public function enter(): void {
    if ($this->depth === 0) {
      $this->correlationId = $this->generateCorrelationId();
    }
    $this->depth++;
  }

  /**
   * Mark the end of a tool call.
   */
  public function leave(): void {
    if ($this->depth > 0) {
      $this->depth--;
    }
    if ($this->depth === 0) {
      $this->correlationId = NULL;
    }
  }

  /**
   * Returns whether a tool call is currently executing.
   */
  public function isActive(): bool {
    return $this->depth > 0;
  }

  /**
   * Returns a correlation ID for the current tool call (if active).
   */
  public function getCorrelationId(): ?string {
    return $this->correlationId;
  }

  /**
   * Generate a correlation ID for log grouping.
   */
  private function generateCorrelationId(): string {
    try {
      return bin2hex(random_bytes(8));
    }
    catch (\Throwable) {
      return substr(hash('sha256', uniqid('', TRUE)), 0, 16);
    }
  }

}
