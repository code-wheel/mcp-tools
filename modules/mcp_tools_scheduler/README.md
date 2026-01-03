# MCP Tools - Scheduler

Schedule content publishing and unpublishing via MCP Tools. Integrates with the Drupal Scheduler contrib module.

## Tools (5)

| Tool | Description | Write Operation |
|------|-------------|-----------------|
| `mcp_scheduler_get_scheduled` | List all content scheduled for publishing/unpublishing | No |
| `mcp_scheduler_publish` | Schedule content for publication at a future date | Yes |
| `mcp_scheduler_unpublish` | Schedule content for unpublication at a future date | Yes |
| `mcp_scheduler_cancel` | Cancel scheduled publish/unpublish | Yes |
| `mcp_scheduler_get_schedule` | Get schedule info for specific content | No |

## Requirements

- mcp_tools (base module)
- scheduler (contrib module) - https://www.drupal.org/project/scheduler

The Scheduler module must be enabled and configured for the content types you want to schedule.

## Installation

```bash
composer require drupal/scheduler
drush en scheduler mcp_tools_scheduler
```

Configure Scheduler for your content types at `/admin/config/content/scheduler`.

## Example Usage

### List All Scheduled Content

```
User: "Show me all content scheduled to be published"

AI calls: mcp_scheduler_get_scheduled(
  type: "publish"
)
```

### Schedule Publication

```
User: "Schedule article 42 to publish on December 25th at 9am"

AI calls: mcp_scheduler_publish(
  entity_id: 42,
  timestamp: "2024-12-25 09:00:00"
)
```

Or using a Unix timestamp:

```
AI calls: mcp_scheduler_publish(
  entity_id: 42,
  timestamp: 1735117200
)
```

### Schedule Unpublication

```
User: "Take down the holiday announcement after New Year's"

AI calls: mcp_scheduler_unpublish(
  entity_id: 55,
  timestamp: "2025-01-02 00:00:00"
)
```

### Check Content Schedule

```
User: "What's the schedule for node 42?"

AI calls: mcp_scheduler_get_schedule(
  entity_id: 42
)

Response:
{
  "success": true,
  "data": {
    "nid": 42,
    "title": "Holiday Announcement",
    "type": "article",
    "status": "unpublished",
    "scheduling_enabled": true,
    "publish_on": {
      "date": "2024-12-25 09:00:00",
      "timestamp": 1735117200
    },
    "unpublish_on": {
      "date": "2025-01-02 00:00:00",
      "timestamp": 1735776000
    },
    "has_schedule": true
  }
}
```

### Cancel Schedule

```
User: "Cancel the scheduled publication for article 42"

AI calls: mcp_scheduler_cancel(
  entity_id: 42,
  type: "publish"
)
```

To cancel both publish and unpublish schedules:

```
AI calls: mcp_scheduler_cancel(
  entity_id: 42,
  type: "all"
)
```

## Timestamp Formats

The `mcp_scheduler_publish` and `mcp_scheduler_unpublish` tools accept timestamps in two formats:

1. **Unix timestamp**: `1735117200`
2. **Date string**: Any format parseable by PHP's `strtotime()`:
   - `"2024-12-25 09:00:00"`
   - `"December 25, 2024 9:00 AM"`
   - `"+1 week"`
   - `"next Monday 10:00"`

## Safety Features

- Write operations require `write` scope via AccessManager
- All write operations are logged via AuditLogger
- Validates that content type has scheduling enabled
- Prevents scheduling dates in the past
- Returns clear error messages when Scheduler is not configured

## How Scheduler Works

The Scheduler module adds two fields to content types:

- `publish_on` - Unix timestamp for scheduled publication
- `unpublish_on` - Unix timestamp for scheduled unpublication

A cron job runs periodically to check if any content has reached its scheduled time and performs the publish/unpublish action automatically.

Make sure cron is running regularly on your site for scheduled actions to execute on time.
