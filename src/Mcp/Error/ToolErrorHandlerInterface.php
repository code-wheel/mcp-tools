<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Error;

use Mcp\Schema\Result\CallToolResult;

/**
 * Formats MCP tool execution errors into CallToolResult responses.
 */
interface ToolErrorHandlerInterface {

  /**
   * Builds a validation error response.
   *
   * @param string $toolName
   *   The tool name.
   * @param array<int, array<string, mixed>> $errors
   *   Validation error details.
   */
  public function validationFailed(string $toolName, array $errors): CallToolResult;

  /**
   * Builds an access denied response.
   */
  public function accessDenied(string $toolName): CallToolResult;

  /**
   * Builds a tool instantiation error response.
   */
  public function instantiationFailed(string $toolName, \Throwable $exception): CallToolResult;

  /**
   * Builds an invalid tool implementation response.
   */
  public function invalidTool(string $toolName, string $message): CallToolResult;

  /**
   * Builds a tool execution error response.
   */
  public function executionFailed(string $toolName, \Throwable $exception): CallToolResult;

}
