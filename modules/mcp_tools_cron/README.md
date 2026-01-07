# MCP Tools - Cron

Manage Drupal cron operations via MCP.

## Tools (5)

| Tool | Description |
|------|-------------|
| `mcp_cron_get_status` | Get cron status (last run, schedule, registered jobs) |
| `mcp_cron_run` | Execute all cron jobs immediately |
| `mcp_cron_run_queue` | Process items from a specific queue |
| `mcp_cron_update_settings` | Update cron autorun threshold |
| `mcp_cron_reset_key` | Generate a new cron key |

## Requirements

- mcp_tools (base module)

## Usage Examples

### Check cron status

```
mcp_cron_get_status()

# Returns:
# - last_run: "2024-01-15 10:30:00"
# - is_overdue: false
# - autorun_threshold: 10800 (3 hours)
# - jobs: [...list of registered cron jobs...]
```

### Run cron immediately

```
mcp_cron_run()

# Returns:
# - success: true
# - duration_seconds: 12.5
# - previous_run: "2024-01-15 10:30:00"
# - current_run: "2024-01-15 14:45:00"
```

### Process a specific queue

```
mcp_cron_run_queue(queue: "aggregator_feeds", limit: 50)

# Returns:
# - processed: 50
# - failed: 0
# - remaining: 120
```

### Update cron interval

```
# Set autorun threshold to 1 hour
mcp_cron_update_settings(threshold: 3600)

# Disable autorun (cron only runs via cron URL or drush)
mcp_cron_update_settings(threshold: 0)
```

### Reset cron key (security)

```
mcp_cron_reset_key()

# Returns new cron key - old cron URL will no longer work
```

## Common Queues

| Queue | Description |
|-------|-------------|
| `aggregator_feeds` | Feed aggregation |
| `locale_translation` | Translation updates |
| `update_fetch_tasks` | Module update checks |
| `cron_queue_1` | Custom queue workers |

## Security

- All write operations require appropriate scope
- Reset cron key requires admin scope
- All operations are audit logged
