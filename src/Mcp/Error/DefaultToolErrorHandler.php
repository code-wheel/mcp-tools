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
    $message = $errors[0]['message'] ?? 'Invalid tool input.';
    $structured = [
      'success' => FALSE,
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
   * {@inheritdoc}
   */
  public function accessDenied(string $toolName): CallToolResult {
    $structured = [
      'success' => FALSE,
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
      'error' => $message,
      'error_code' => ErrorCode::EXECUTION_FAILED,
      'tool' => $toolName,
      'remediation' => 'Inspect logs and retry with smaller inputs or after correcting site state.',
    ];

    $content = [new TextContent('Tool execution failed: ' . $message)];

    return new CallToolResult($content, TRUE, $structured);
  }

}
