# MCP Tools - Ultimate Cron

Manage Ultimate Cron jobs via MCP Tools. Provides tools to list, inspect, run, enable, disable, and view logs for Ultimate Cron jobs.

## Tools (6)

| Tool | Description | Write Operation |
|------|-------------|-----------------|
| `mcp_ultimate_cron_list_jobs` | List all Ultimate Cron jobs with their status | No |
| `mcp_ultimate_cron_get_job` | Get detailed information about a specific job | No |
| `mcp_ultimate_cron_run` | Execute a specific job immediately | Yes |
| `mcp_ultimate_cron_enable` | Enable a disabled job | Yes |
| `mcp_ultimate_cron_disable` | Disable a job to prevent it from running | Yes |
| `mcp_ultimate_cron_logs` | Get recent execution logs for a job | No |

## Requirements

- mcp_tools (base module)
- ultimate_cron (contrib module) - https://www.drupal.org/project/ultimate_cron

## Installation

```bash
composer require drupal/ultimate_cron
drush en ultimate_cron mcp_tools_ultimate_cron
```

## Usage Examples

### List All Cron Jobs

```
User: "Show me all Ultimate Cron jobs"

AI calls: mcp_ultimate_cron_list_jobs()

Response:
{
  "success": true,
  "data": {
    "jobs": [
      {
        "id": "system_cron",
        "title": "System cron",
        "module": "system",
        "status": "enabled",
        "is_locked": false,
        "last_run": "2024-01-15 10:30:00",
        "last_duration": 2.5,
        "last_status": "info"
      },
      ...
    ],
    "count": 15
  }
}
```

### Get Job Details

```
User: "Show me details for the system_cron job"

AI calls: mcp_ultimate_cron_get_job(
  id: "system_cron"
)

Response:
{
  "success": true,
  "data": {
    "id": "system_cron",
    "title": "System cron",
    "module": "system",
    "callback": "system_cron",
    "status": "enabled",
    "is_locked": false,
    "scheduler": {
      "id": "simple",
      "configuration": {
        "rules": ["*/15 * * * *"]
      }
    },
    "last_run": {
      "start_time": "2024-01-15 10:30:00",
      "end_time": "2024-01-15 10:30:02",
      "duration": 2.5,
      "message": "Completed successfully",
      "severity": "info"
    }
  }
}
```

### Run a Job Immediately

```
User: "Run the search_cron job now"

AI calls: mcp_ultimate_cron_run(
  id: "search_cron"
)

Response:
{
  "success": true,
  "message": "Job 'search_cron' executed successfully.",
  "data": {
    "id": "search_cron",
    "title": "Search index",
    "duration_seconds": 5.23,
    "executed_at": "2024-01-15 14:45:00"
  }
}
```

### Enable a Disabled Job

```
User: "Enable the aggregator_cron job"

AI calls: mcp_ultimate_cron_enable(
  id: "aggregator_cron"
)

Response:
{
  "success": true,
  "message": "Job 'aggregator_cron' has been enabled.",
  "data": {
    "id": "aggregator_cron",
    "title": "Feed aggregation",
    "status": "enabled",
    "changed": true
  }
}
```

### Disable a Job

```
User: "Disable the statistics_cron job"

AI calls: mcp_ultimate_cron_disable(
  id: "statistics_cron"
)

Response:
{
  "success": true,
  "message": "Job 'statistics_cron' has been disabled.",
  "data": {
    "id": "statistics_cron",
    "title": "Statistics",
    "status": "disabled",
    "changed": true
  }
}
```

### View Job Logs

```
User: "Show me the last 10 runs of system_cron"

AI calls: mcp_ultimate_cron_logs(
  id: "system_cron",
  limit: 10
)

Response:
{
  "success": true,
  "data": {
    "job_id": "system_cron",
    "job_title": "System cron",
    "logs": [
      {
        "lid": "12345",
        "start_time": "2024-01-15 10:30:00",
        "end_time": "2024-01-15 10:30:02",
        "duration": 2.5,
        "init_message": "",
        "message": "Completed successfully",
        "severity": "info",
        "severity_code": 6
      },
      ...
    ],
    "count": 10,
    "limit": 10
  }
}
```

## Log Severity Levels

Log entries include severity levels following RFC 5424:

| Code | Level | Description |
|------|-------|-------------|
| 0 | emergency | System is unusable |
| 1 | alert | Action must be taken immediately |
| 2 | critical | Critical conditions |
| 3 | error | Error conditions |
| 4 | warning | Warning conditions |
| 5 | notice | Normal but significant conditions |
| 6 | info | Informational messages |
| 7 | debug | Debug-level messages |

## Security

- Write operations (run, enable, disable) require appropriate scope via AccessManager
- All write operations are logged via AuditLogger
- Read operations (list, get, logs) are available with standard access
- Locked jobs cannot be run until they are unlocked

## Common Job IDs

Typical Ultimate Cron job IDs follow the pattern `{module}_cron`:

| Job ID | Description |
|--------|-------------|
| `system_cron` | Core system cron tasks |
| `search_cron` | Search indexing |
| `update_cron` | Module update checks |
| `aggregator_cron` | Feed aggregation |
| `dblog_cron` | Database log cleanup |
| `field_cron` | Field API cron tasks |
| `file_cron` | Temporary file cleanup |
| `history_cron` | History cleanup |
| `locale_cron` | Translation updates |
| `node_cron` | Node-related cron tasks |

Use `mcp_ultimate_cron_list_jobs` to see all available jobs on your site.
