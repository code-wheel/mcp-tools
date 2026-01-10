<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Error;

use CodeWheel\McpErrorCodes\ErrorCode;
use CodeWheel\McpErrorCodes\McpError;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

/**
 * Default MCP tool error handler using McpError fluent builder.
 */
class DefaultToolErrorHandler implements ToolErrorHandlerInterface {

  public function __construct(
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function validationFailed(string $toolName, array $errors): CallToolResult {
    $message = $errors[0]['message'] ?? 'Invalid tool input.';
    $field = $errors[0]['field'] ?? 'input';

    $error = McpError::custom(ErrorCode::VALIDATION_ERROR, $message)
      ->withDetail('field', $field)
      ->withContext(['validation_errors' => $errors])
      ->withSuggestion('Review required fields and input types for this tool, then retry.');

    return $this->buildResult($error, $toolName, $errors);
  }

  /**
   * {@inheritdoc}
   */
  public function accessDenied(string $toolName): CallToolResult {
    $error = McpError::accessDenied($toolName)
      ->withSuggestion('Ensure the MCP scopes and Drupal permissions allow this operation.');

    return $this->buildResult($error, $toolName);
  }

  /**
   * {@inheritdoc}
   */
  public function instantiationFailed(string $toolName, \Throwable $exception): CallToolResult {
    $this->logger->error('Failed to create tool instance: @tool | @message', [
      '@tool' => $toolName,
      '@message' => $exception->getMessage(),
    ]);

    $error = McpError::custom(ErrorCode::INSTANTIATION_FAILED, 'Tool instantiation failed: ' . $exception->getMessage())
      ->withSuggestion('Verify the tool plugin is installed and its dependencies are available.');

    return $this->buildResult($error, $toolName);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidTool(string $toolName, string $message): CallToolResult {
    $error = McpError::custom(ErrorCode::INVALID_TOOL, $message)
      ->withSuggestion('Confirm the tool implementation matches the Tool API contract.');

    return $this->buildResult($error, $toolName);
  }

  /**
   * {@inheritdoc}
   */
  public function executionFailed(string $toolName, \Throwable $exception): CallToolResult {
    $this->logger->error('Tool execution failed: @tool | @message', [
      '@tool' => $toolName,
      '@message' => $exception->getMessage(),
    ]);

    $error = McpError::custom(ErrorCode::EXECUTION_FAILED, $exception->getMessage())
      ->withSuggestion('Inspect logs and retry with smaller inputs or after correcting site state.');

    return $this->buildResult($error, $toolName);
  }

  /**
   * Builds a CallToolResult from an McpError.
   *
   * @param \CodeWheel\McpErrorCodes\McpError $error
   *   The error builder.
   * @param string $toolName
   *   The tool name.
   * @param array|null $validationErrors
   *   Optional validation errors array.
   *
   * @return \Mcp\Schema\Result\CallToolResult
   *   The call tool result.
   */
  private function buildResult(McpError $error, string $toolName, ?array $validationErrors = NULL): CallToolResult {
    $data = $error->toArray();

    // Build structured content with consistent format.
    $structured = [
      'success' => FALSE,
      'error' => $data['error'],
      'error_code' => $data['code'],
      'tool' => $toolName,
    ];

    // Add validation errors if present.
    if ($validationErrors !== NULL) {
      $structured['validation_errors'] = $validationErrors;
    }

    // Add remediation hint if available.
    if ($error->getSuggestion() !== NULL) {
      $structured['remediation'] = $error->getSuggestion();
    }

    $text = $data['error'] . "\n" . json_encode($structured, JSON_PRETTY_PRINT);

    return new CallToolResult([new TextContent($text)], TRUE, $structured);
  }

}
