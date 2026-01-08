<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_observability\EventSubscriber;

use Drupal\mcp_tools\Mcp\Event\ToolExecutionFailedEvent;
use Drupal\mcp_tools\Mcp\Event\ToolExecutionStartedEvent;
use Drupal\mcp_tools\Mcp\Event\ToolExecutionSucceededEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Logs MCP tool execution events to the watchdog channel.
 */
final class ToolExecutionLogSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ToolExecutionStartedEvent::class => 'onStarted',
      ToolExecutionSucceededEvent::class => 'onSucceeded',
      ToolExecutionFailedEvent::class => 'onFailed',
    ];
  }

  public function onStarted(ToolExecutionStartedEvent $event): void {
    $this->logger->debug('MCP tool execution started: @tool', [
      '@tool' => $event->toolName,
      'tool_name' => $event->toolName,
      'plugin_id' => $event->pluginId,
      'request_id' => $event->requestId,
      'arguments' => $event->arguments,
      'timestamp' => $event->timestamp,
    ]);
  }

  public function onSucceeded(ToolExecutionSucceededEvent $event): void {
    $this->logger->info('MCP tool execution succeeded: @tool (@duration_ms ms)', [
      '@tool' => $event->toolName,
      '@duration_ms' => number_format($event->durationMs, 2, '.', ''),
      'tool_name' => $event->toolName,
      'plugin_id' => $event->pluginId,
      'request_id' => $event->requestId,
      'duration_ms' => $event->durationMs,
      'arguments' => $event->arguments,
      'structured' => $event->result->structuredContent ?? NULL,
    ]);
  }

  public function onFailed(ToolExecutionFailedEvent $event): void {
    $context = [
      '@tool' => $event->toolName,
      '@duration_ms' => number_format($event->durationMs, 2, '.', ''),
      'tool_name' => $event->toolName,
      'plugin_id' => $event->pluginId,
      'request_id' => $event->requestId,
      'duration_ms' => $event->durationMs,
      'reason' => $event->reason,
      'arguments' => $event->arguments,
      'structured' => $event->result?->structuredContent ?? NULL,
    ];

    if ($event->exception) {
      $context['exception'] = $event->exception;
      $context['exception_message'] = $event->exception->getMessage();
    }

    $message = 'MCP tool execution failed (@reason): @tool (@duration_ms ms)';
    $context['@reason'] = $event->reason;

    if ($event->reason === ToolExecutionFailedEvent::REASON_EXECUTION) {
      $this->logger->error($message, $context);
      return;
    }

    if ($event->reason === ToolExecutionFailedEvent::REASON_POLICY_DRY_RUN) {
      $this->logger->info($message, $context);
      return;
    }

    $this->logger->warning($message, $context);
  }

}
