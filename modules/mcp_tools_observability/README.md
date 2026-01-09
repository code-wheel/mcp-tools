# MCP Tools - Observability

Event subscribers for MCP tool execution monitoring and logging.

## Overview

This module provides observability adapters that subscribe to MCP tool execution events and log them to Drupal's watchdog (dblog). Use this for debugging, auditing, and monitoring tool usage.

## Features

- Logs all tool executions to watchdog
- Captures execution duration and status
- Records sanitized arguments (sensitive data redacted)
- Tracks success/failure with reason codes

## Requirements

- mcp_tools (base module)

## Installation

```bash
drush en mcp_tools_observability
```

## Events

This module subscribes to the following events:

| Event | Description |
|-------|-------------|
| `ToolExecutionStartedEvent` | Fired when a tool begins execution |
| `ToolExecutionSucceededEvent` | Fired when a tool completes successfully |
| `ToolExecutionFailedEvent` | Fired when a tool fails (with reason code) |

## Failure Reason Codes

| Code | Description |
|------|-------------|
| `REASON_VALIDATION` | Input validation failed |
| `REASON_ACCESS_DENIED` | Permission or scope denied |
| `REASON_RATE_LIMITED` | Rate limit exceeded |
| `REASON_EXCEPTION` | Unexpected exception thrown |

## Viewing Logs

View tool execution logs at `/admin/reports/dblog`:

```bash
# Or via Drush
drush watchdog:show --type=mcp_tools
```

## Custom Observers

To add your own observer (e.g., external metrics, Sentry):

```php
namespace Drupal\my_module\EventSubscriber;

use CodeWheel\McpEvents\ToolExecutionSucceededEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MyToolObserver implements EventSubscriberInterface {
  public static function getSubscribedEvents() {
    return [
      ToolExecutionSucceededEvent::class => 'onSuccess',
    ];
  }

  public function onSuccess(ToolExecutionSucceededEvent $event): void {
    // Send to external system
    $this->metrics->increment('mcp_tools.success', [
      'tool' => $event->getToolName(),
      'duration' => $event->getDuration(),
    ]);
  }
}
```

## Performance

The watchdog subscriber is lightweight, but for high-volume production sites, consider:

- Using a faster logging backend (syslog, file)
- Sampling events instead of logging all
- Disabling this module and using webhooks instead (`mcp_tools.settings.webhooks`)
