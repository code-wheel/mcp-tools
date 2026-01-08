<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Event;

/**
 * Dispatched when a Tool API execution begins.
 */
final class ToolExecutionStartedEvent {

  /**
   * @param string $toolName
   *   MCP tool name.
   * @param string $pluginId
   *   Tool API plugin ID.
   * @param array<string, mixed> $arguments
   *   Sanitized tool arguments.
   * @param string|int|null $requestId
   *   MCP request id.
   * @param float $timestamp
   *   UNIX timestamp (microtime) for start.
   */
  public function __construct(
    public readonly string $toolName,
    public readonly string $pluginId,
    public readonly array $arguments,
    public readonly string|int|null $requestId,
    public readonly float $timestamp,
  ) {}

}
