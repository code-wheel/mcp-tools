<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

/**
 * Tracks whether an MCP tool call is currently executing.
 *
 * This is used to scope side effects (like config-change tracking) to MCP
 * invocations only, without affecting normal Drupal admin actions.
 */
final class McpToolCallContext {

  private int $depth = 0;

  /**
   * Mark the beginning of a tool call.
   */
  public function enter(): void {
    $this->depth++;
  }

  /**
   * Mark the end of a tool call.
   */
  public function leave(): void {
    if ($this->depth > 0) {
      $this->depth--;
    }
  }

  /**
   * Returns whether a tool call is currently executing.
   */
  public function isActive(): bool {
    return $this->depth > 0;
  }

}

