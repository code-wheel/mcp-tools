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
    $this->assertSame('VALIDATION_ERROR', $structured['error_code']);
    $this->assertSame('test_tool', $structured['tool']);
    $this->assertSame('Field is required; Invalid type', $structured['error']);
    $this->assertCount(2, $structured['validation_errors']);
    $this->assertArrayHasKey('remediation', $structured);
  }

  /**
   * The headline is the specific problem and where it is, not the wrapper.
   */
  public function testValidationHeadlineNamesThePropertyAndTheProblem(): void {
    $errors = [
      ['message' => 'The properties must match schema: nid', 'keyword' => 'properties', 'path' => NULL],
      ['message' => 'The data (string) must match the type: integer', 'keyword' => 'type', 'path' => 'nid'],
    ];

    $result = $this->handler->validationFailed('test_tool', $errors);

    $this->assertSame('nid: The data (string) must match the type: integer', $result->structuredContent['error']);
    $this->assertStringStartsWith('nid: The data (string) must match the type: integer', $result->content[0]->text);
    $this->assertCount(2, $result->structuredContent['validation_errors']);
  }

  /**
   * An exception's long text is cut in what the model reads, not in the data.
   */
  public function testLongExceptionIsCutInTheTextOnly(): void {
    $long = 'The "entity:node:gone" plugin does not exist. Valid plugin IDs are: ' . str_repeat('entity_revision:some_plugin, ', 150);

    $result = $this->handler->executionFailed('test_tool', new \RuntimeException($long));

    $this->assertLessThan(1700, mb_strlen($result->content[0]->text));
    $this->assertStringContainsString('characters cut', $result->content[0]->text);
    $this->assertSame($long, $result->structuredContent['message']);
  }

  /**
   * The text gives the problem once: it was there three times.
   */
  public function testValidationTextGivesTheProblemOnce(): void {
    $errors = [['message' => 'The data (string) must match the type: integer', 'keyword' => 'type', 'path' => 'nid']];

    $result = $this->handler->validationFailed('test_tool', $errors);

    $this->assertSame(1, substr_count($result->content[0]->text, 'nid: The data (string) must match the type: integer'));
    $this->assertStringContainsString('validation_errors', $result->content[0]->text);
    $this->assertSame($result->structuredContent['message'], $result->structuredContent['error']);
  }

  public function testValidationFailedWithEmptyErrors(): void {
    $result = $this->handler->validationFailed('test_tool', []);

    $structured = $result->structuredContent;
    $this->assertSame('Invalid tool input.', $structured['error']);
  }

  /**
   * Every error result has the keys the advertised outputSchema requires.
   *
   * The schema of each tool requires "success" and "message". Error results
   * only had "error", and the official JS client checks error results too:
   * it replaced our error with "Structured content does not match the tool's
   * output schema", so the model never saw what was wrong.
   */
  public function testErrorResultsCarryTheKeysTheOutputSchemaRequires(): void {
    $handler = new DefaultToolErrorHandler($this->createMock(LoggerInterface::class));
    $results = [
      'validation' => $handler->validationFailed('t', [['message' => 'The data (string) must match the type: integer', 'path' => '/nid', 'keyword' => 'type']]),
      'access' => $handler->accessDenied('t'),
      'instantiation' => $handler->instantiationFailed('t', new \RuntimeException('no plugin')),
      'invalid' => $handler->invalidTool('t', 'Not a tool.'),
      'execution' => $handler->executionFailed('t', new \RuntimeException('boom')),
    ];

    foreach ($results as $kind => $result) {
      $structured = $result->structuredContent;
      $this->assertFalse($structured['success'], $kind);
      $this->assertIsString($structured['message'] ?? NULL, $kind);
      $this->assertNotSame('', $structured['message'], $kind);
      // "error" stays for clients that read it.
      $this->assertSame($structured['error'], $structured['message'], $kind);
    }
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
