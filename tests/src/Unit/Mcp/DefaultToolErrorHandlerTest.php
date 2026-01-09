<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\mcp_tools\Mcp\Error\DefaultToolErrorHandler;
use Mcp\Schema\Result\CallToolResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for DefaultToolErrorHandler.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\Error\DefaultToolErrorHandler::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class DefaultToolErrorHandlerTest extends TestCase {

  private LoggerInterface $logger;
  private DefaultToolErrorHandler $handler;

  protected function setUp(): void {
    parent::setUp();
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->handler = new DefaultToolErrorHandler($this->logger);
  }

  public function testValidationFailedReturnsErrorResult(): void {
    $errors = [
      ['message' => 'Field is required', 'field' => 'name'],
      ['message' => 'Invalid type', 'field' => 'count'],
    ];

    $result = $this->handler->validationFailed('test_tool', $errors);

    $this->assertInstanceOf(CallToolResult::class, $result);
    $this->assertTrue($result->isError);

    $structured = $result->structuredContent;
    $this->assertFalse($structured['success']);
    $this->assertSame('VALIDATION_FAILED', $structured['error_code']);
    $this->assertSame('test_tool', $structured['tool']);
    $this->assertSame('Field is required', $structured['error']);
    $this->assertCount(2, $structured['validation_errors']);
    $this->assertArrayHasKey('remediation', $structured);
  }

  public function testValidationFailedWithEmptyErrors(): void {
    $result = $this->handler->validationFailed('test_tool', []);

    $structured = $result->structuredContent;
    $this->assertSame('Invalid tool input.', $structured['error']);
  }

  public function testAccessDeniedReturnsErrorResult(): void {
    $result = $this->handler->accessDenied('restricted_tool');

    $this->assertInstanceOf(CallToolResult::class, $result);
    $this->assertTrue($result->isError);

    $structured = $result->structuredContent;
    $this->assertFalse($structured['success']);
    $this->assertSame('ACCESS_DENIED', $structured['error_code']);
    $this->assertSame('restricted_tool', $structured['tool']);
    $this->assertSame('Access denied.', $structured['error']);
    $this->assertArrayHasKey('remediation', $structured);
  }

  public function testInstantiationFailedLogsAndReturnsError(): void {
    $exception = new \RuntimeException('Missing dependency');

    $this->logger->expects($this->once())
      ->method('error')
      ->with(
        'Failed to create tool instance: @tool | @message',
        $this->callback(function (array $context): bool {
          return $context['@tool'] === 'broken_tool'
            && $context['@message'] === 'Missing dependency';
        })
      );

    $result = $this->handler->instantiationFailed('broken_tool', $exception);

    $this->assertInstanceOf(CallToolResult::class, $result);
    $this->assertTrue($result->isError);

    $structured = $result->structuredContent;
    $this->assertFalse($structured['success']);
    $this->assertSame('INSTANTIATION_FAILED', $structured['error_code']);
    $this->assertSame('broken_tool', $structured['tool']);
    $this->assertStringContainsString('Missing dependency', $structured['error']);
    $this->assertArrayHasKey('remediation', $structured);
  }

  public function testInvalidToolReturnsError(): void {
    $result = $this->handler->invalidTool('bad_tool', 'Tool does not implement required interface');

    $this->assertInstanceOf(CallToolResult::class, $result);
    $this->assertTrue($result->isError);

    $structured = $result->structuredContent;
    $this->assertFalse($structured['success']);
    $this->assertSame('INVALID_TOOL', $structured['error_code']);
    $this->assertSame('bad_tool', $structured['tool']);
    $this->assertSame('Tool does not implement required interface', $structured['error']);
    $this->assertArrayHasKey('remediation', $structured);
  }

  public function testExecutionFailedLogsAndReturnsError(): void {
    $exception = new \Exception('Database connection lost');

    $this->logger->expects($this->once())
      ->method('error')
      ->with(
        'Tool execution failed: @tool | @message',
        $this->callback(function (array $context): bool {
          return $context['@tool'] === 'db_tool'
            && $context['@message'] === 'Database connection lost';
        })
      );

    $result = $this->handler->executionFailed('db_tool', $exception);

    $this->assertInstanceOf(CallToolResult::class, $result);
    $this->assertTrue($result->isError);

    $structured = $result->structuredContent;
    $this->assertFalse($structured['success']);
    $this->assertSame('EXECUTION_FAILED', $structured['error_code']);
    $this->assertSame('db_tool', $structured['tool']);
    $this->assertSame('Database connection lost', $structured['error']);
    $this->assertArrayHasKey('remediation', $structured);
  }

}
