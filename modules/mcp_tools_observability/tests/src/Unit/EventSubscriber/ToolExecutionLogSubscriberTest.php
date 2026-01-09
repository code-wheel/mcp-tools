<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_observability\Unit\EventSubscriber;

use CodeWheel\McpEvents\ToolExecutionFailedEvent;
use CodeWheel\McpEvents\ToolExecutionStartedEvent;
use CodeWheel\McpEvents\ToolExecutionSucceededEvent;
use Drupal\mcp_tools_observability\EventSubscriber\ToolExecutionLogSubscriber;
use Mcp\Types\CallToolResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for ToolExecutionLogSubscriber.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_observability\EventSubscriber\ToolExecutionLogSubscriber::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_observability')]
final class ToolExecutionLogSubscriberTest extends TestCase {

  private LoggerInterface $logger;
  private ToolExecutionLogSubscriber $subscriber;

  protected function setUp(): void {
    parent::setUp();

    // Skip tests if MCP SDK types aren't available.
    if (!class_exists(CallToolResult::class)) {
      $this->markTestSkipped('MCP SDK is not installed.');
    }

    $this->logger = $this->createMock(LoggerInterface::class);
    $this->subscriber = new ToolExecutionLogSubscriber($this->logger);
  }

  public function testGetSubscribedEventsReturnsExpectedEvents(): void {
    $events = ToolExecutionLogSubscriber::getSubscribedEvents();

    $this->assertArrayHasKey(ToolExecutionStartedEvent::class, $events);
    $this->assertArrayHasKey(ToolExecutionSucceededEvent::class, $events);
    $this->assertArrayHasKey(ToolExecutionFailedEvent::class, $events);
    $this->assertSame('onStarted', $events[ToolExecutionStartedEvent::class]);
    $this->assertSame('onSucceeded', $events[ToolExecutionSucceededEvent::class]);
    $this->assertSame('onFailed', $events[ToolExecutionFailedEvent::class]);
  }

  public function testOnStartedLogsDebugMessage(): void {
    $event = new ToolExecutionStartedEvent(
      toolName: 'mcp_tools___get_status',
      pluginId: 'mcp_tools:get_status',
      requestId: 'req-123',
      arguments: ['verbose' => TRUE],
      timestamp: 1704067200.0,
    );

    $this->logger->expects($this->once())
      ->method('debug')
      ->with(
        'MCP tool execution started: @tool',
        $this->callback(function (array $context): bool {
          return $context['@tool'] === 'mcp_tools___get_status'
            && $context['tool_name'] === 'mcp_tools___get_status'
            && $context['plugin_id'] === 'mcp_tools:get_status'
            && $context['request_id'] === 'req-123'
            && $context['arguments'] === ['verbose' => TRUE];
        })
      );

    $this->subscriber->onStarted($event);
  }

  public function testOnSucceededLogsInfoMessage(): void {
    $result = $this->createMock(CallToolResult::class);
    $result->structuredContent = ['status' => 'ok'];

    $event = new ToolExecutionSucceededEvent(
      toolName: 'mcp_tools___get_status',
      pluginId: 'mcp_tools:get_status',
      requestId: 'req-123',
      arguments: [],
      result: $result,
      durationMs: 42.5,
    );

    $this->logger->expects($this->once())
      ->method('info')
      ->with(
        'MCP tool execution succeeded: @tool (@duration_ms ms)',
        $this->callback(function (array $context): bool {
          return $context['@tool'] === 'mcp_tools___get_status'
            && $context['@duration_ms'] === '42.50'
            && $context['duration_ms'] === 42.5;
        })
      );

    $this->subscriber->onSucceeded($event);
  }

  public function testOnFailedLogsErrorForExecutionFailure(): void {
    $exception = new \RuntimeException('Something went wrong');

    $event = new ToolExecutionFailedEvent(
      toolName: 'mcp_tools___create_node',
      pluginId: 'mcp_tools:create_node',
      requestId: 'req-456',
      arguments: ['type' => 'article'],
      reason: ToolExecutionFailedEvent::REASON_EXECUTION,
      durationMs: 123.45,
      result: NULL,
      exception: $exception,
    );

    $this->logger->expects($this->once())
      ->method('error')
      ->with(
        'MCP tool execution failed (@reason): @tool (@duration_ms ms)',
        $this->callback(function (array $context) use ($exception): bool {
          return $context['@tool'] === 'mcp_tools___create_node'
            && $context['@reason'] === ToolExecutionFailedEvent::REASON_EXECUTION
            && $context['exception'] === $exception
            && $context['exception_message'] === 'Something went wrong';
        })
      );

    $this->subscriber->onFailed($event);
  }

  public function testOnFailedLogsInfoForDryRunPolicy(): void {
    $event = new ToolExecutionFailedEvent(
      toolName: 'mcp_tools___delete_node',
      pluginId: 'mcp_tools:delete_node',
      requestId: 'req-789',
      arguments: ['nid' => 1],
      reason: ToolExecutionFailedEvent::REASON_POLICY_DRY_RUN,
      durationMs: 5.0,
      result: NULL,
      exception: NULL,
    );

    $this->logger->expects($this->once())
      ->method('info')
      ->with(
        'MCP tool execution failed (@reason): @tool (@duration_ms ms)',
        $this->callback(function (array $context): bool {
          return $context['@reason'] === ToolExecutionFailedEvent::REASON_POLICY_DRY_RUN;
        })
      );

    $this->subscriber->onFailed($event);
  }

  public function testOnFailedLogsWarningForOtherReasons(): void {
    $event = new ToolExecutionFailedEvent(
      toolName: 'mcp_tools___update_node',
      pluginId: 'mcp_tools:update_node',
      requestId: 'req-abc',
      arguments: ['nid' => 1],
      reason: ToolExecutionFailedEvent::REASON_VALIDATION,
      durationMs: 10.0,
      result: NULL,
      exception: NULL,
    );

    $this->logger->expects($this->once())
      ->method('warning')
      ->with(
        'MCP tool execution failed (@reason): @tool (@duration_ms ms)',
        $this->callback(function (array $context): bool {
          return $context['@reason'] === ToolExecutionFailedEvent::REASON_VALIDATION;
        })
      );

    $this->subscriber->onFailed($event);
  }

  public function testOnFailedWithoutException(): void {
    $event = new ToolExecutionFailedEvent(
      toolName: 'mcp_tools___get_status',
      pluginId: 'mcp_tools:get_status',
      requestId: 'req-def',
      arguments: [],
      reason: ToolExecutionFailedEvent::REASON_ACCESS_DENIED,
      durationMs: 1.0,
      result: NULL,
      exception: NULL,
    );

    $this->logger->expects($this->once())
      ->method('warning')
      ->with(
        'MCP tool execution failed (@reason): @tool (@duration_ms ms)',
        $this->callback(function (array $context): bool {
          return !isset($context['exception'])
            && !isset($context['exception_message']);
        })
      );

    $this->subscriber->onFailed($event);
  }

}
