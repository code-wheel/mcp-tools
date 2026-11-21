<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Error;

use CodeWheel\McpErrorCodes\ErrorCode;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

/**
 * Default MCP tool error handler.
 */
class DefaultToolErrorHandler implements ToolErrorHandlerInterface {

  public function __construct(
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function validationFailed(string $toolName, array $errors): CallToolResult {
    // Every result here carries "message" next to "error". The outputSchema
    // of each tool requires "success" and "message", and clients check error
    // results against it too: without "message" the official JS client
    // replaces the error with a schema complaint of its own.
    $message = $this->validationHeadline($errors);
    $structured = [
      'success' => FALSE,
      'message' => $message,
      'error' => $message,
      'error_code' => ErrorCode::VALIDATION_ERROR,
      'tool' => $toolName,
      'validation_errors' => $errors,
      'remediation' => 'Review required fields and input types for this tool, then retry.',
    ];

    $text = $message . "\n" . json_encode($structured, JSON_PRETTY_PRINT);

    return new CallToolResult([new TextContent($text)], TRUE, $structured);
  }

  /**
   * Builds the one-line summary of what is wrong with the input.
   *
   * A "properties" error only says that some property failed; the errors
   * under it say which one and why, so those are the ones worth showing.
   *
   * @param array<int, array<string, mixed>> $errors
   *   The validation errors.
   */
  private function validationHeadline(array $errors): string {
    $specific = array_filter($errors, static fn(array $error): bool => ($error['keyword'] ?? '') !== 'properties');
    $parts = [];
    foreach ($specific ?: $errors as $error) {
      $text = (string) ($error['message'] ?? '');
      if ($text === '') {
        continue;
      }
      $path = (string) ($error['path'] ?? '');
      $parts[] = $path !== '' ? $path . ': ' . $text : $text;
    }
    $parts = array_slice(array_values(array_unique($parts)), 0, 5);
    return $parts !== [] ? implode('; ', $parts) : 'Invalid tool input.';
  }

  /**
   * {@inheritdoc}
   */
  public function accessDenied(string $toolName): CallToolResult {
    $structured = [
      'success' => FALSE,
      'message' => 'Access denied.',
      'error' => 'Access denied.',
      'error_code' => ErrorCode::ACCESS_DENIED,
      'tool' => $toolName,
      'remediation' => 'Ensure the MCP scopes and Drupal permissions allow this operation.',
    ];

    return new CallToolResult([new TextContent('Access denied.')], TRUE, $structured);
  }

  /**
   * {@inheritdoc}
   */
  public function instantiationFailed(string $toolName, \Throwable $exception): CallToolResult {
    $this->logger->error('Failed to create tool instance: @tool | @message', [
      '@tool' => $toolName,
      '@message' => $exception->getMessage(),
    ]);

    $message = 'Tool instantiation failed: ' . $exception->getMessage();
    $structured = [
      'success' => FALSE,
      'message' => $message,
      'error' => $message,
      'error_code' => ErrorCode::INSTANTIATION_FAILED,
      'tool' => $toolName,
      'remediation' => 'Verify the tool plugin is installed and its dependencies are available.',
    ];
    $content = [new TextContent($message)];

    return new CallToolResult($content, TRUE, $structured);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidTool(string $toolName, string $message): CallToolResult {
    $structured = [
      'success' => FALSE,
      'message' => $message,
      'error' => $message,
      'error_code' => ErrorCode::INVALID_TOOL,
      'tool' => $toolName,
      'remediation' => 'Confirm the tool implementation matches the Tool API contract.',
    ];
    $content = [new TextContent($message)];

    return new CallToolResult($content, TRUE, $structured);
  }

  /**
   * {@inheritdoc}
   */
  public function executionFailed(string $toolName, \Throwable $exception): CallToolResult {
    $this->logger->error('Tool execution failed: @tool | @message', [
      '@tool' => $toolName,
      '@message' => $exception->getMessage(),
    ]);

    $message = $exception->getMessage();
    $structured = [
      'success' => FALSE,
      'message' => $message,
      'error' => $message,
      'error_code' => ErrorCode::EXECUTION_FAILED,
      'tool' => $toolName,
      'remediation' => 'Inspect logs and retry with smaller inputs or after correcting site state.',
    ];

    $content = [new TextContent('Tool execution failed: ' . $message)];

    return new CallToolResult($content, TRUE, $structured);
  }

}
