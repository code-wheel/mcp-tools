<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Event;

use Mcp\Schema\Result\CallToolResult;

/**
 * Dispatched when a Tool API execution fails.
 */
final class ToolExecutionFailedEvent {

  public const REASON_VALIDATION = 'validation_failed';
  public const REASON_ACCESS_DENIED = 'access_denied';
  public const REASON_INSTANTIATION = 'instantiation_failed';
  public const REASON_INVALID_TOOL = 'invalid_tool';
  public const REASON_RESULT = 'result_failed';
  public const REASON_EXECUTION = 'execution_failed';
  public const REASON_POLICY = 'policy_blocked';
  public const REASON_POLICY_APPROVAL = 'policy_approval_required';
  public const REASON_POLICY_BUDGET = 'policy_budget_exceeded';
  public const REASON_POLICY_DRY_RUN = 'policy_dry_run';
  public const REASON_POLICY_SCOPE = 'policy_scope_required';

  /**
   * @param string $toolName
   *   MCP tool name.
   * @param string $pluginId
   *   Tool API plugin ID.
   * @param array<string, mixed> $arguments
   *   Sanitized tool arguments.
   * @param string $reason
   *   Failure reason.
   * @param \Mcp\Schema\Result\CallToolResult|null $result
   *   MCP call tool result if available.
   * @param \Throwable|null $exception
   *   Exception thrown during execution, if any.
   * @param float $durationMs
   *   Duration in milliseconds.
   * @param string|int|null $requestId
   *   MCP request id.
   */
  public function __construct(
    public readonly string $toolName,
    public readonly string $pluginId,
    public readonly array $arguments,
    public readonly string $reason,
    public readonly ?CallToolResult $result,
    public readonly ?\Throwable $exception,
    public readonly float $durationMs,
    public readonly string|int|null $requestId,
  ) {}

}
