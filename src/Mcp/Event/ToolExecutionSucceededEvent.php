<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Event;

use Mcp\Schema\Result\CallToolResult;

/**
 * Dispatched when a Tool API execution succeeds.
 */
final class ToolExecutionSucceededEvent {

  /**
   * @param string $toolName
   *   MCP tool name.
   * @param string $pluginId
   *   Tool API plugin ID.
   * @param array<string, mixed> $arguments
   *   Sanitized tool arguments.
   * @param \Mcp\Schema\Result\CallToolResult $result
   *   MCP call tool result.
   * @param float $durationMs
   *   Duration in milliseconds.
   * @param string|int|null $requestId
   *   MCP request id.
   */
  public function __construct(
    public readonly string $toolName,
    public readonly string $pluginId,
    public readonly array $arguments,
    public readonly CallToolResult $result,
    public readonly float $durationMs,
    public readonly string|int|null $requestId,
  ) {}

}
