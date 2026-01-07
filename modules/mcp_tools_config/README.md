# MCP Tools - Config

Configuration management for MCP Tools.

## Tools (5)

| Tool | Description |
|------|-------------|
| `mcp_config_changes` | List config that differs from sync directory |
| `mcp_config_export` | Export configuration to sync directory |
| `mcp_config_mcp_changes` | List config created/modified via MCP |
| `mcp_config_diff` | Show diff between active and sync config |
| `mcp_config_preview` | Dry-run mode: preview what an operation would do |

## Why This Matters

MCP Tools creates configuration directly in the database. This can cause **configuration drift** if changes aren't exported to your Git repository.

```
Developer commits config to Git
  ↓
Deploys to production
  ↓
AI creates new content type via MCP (in database only!)
  ↓
Next deploy: Config conflict or overwrite!
```

This submodule helps you track and export MCP-created configuration.

## Requirements

- mcp_tools (base module)

## Installation

```bash
drush en mcp_tools_config
```

## Example Usage

### Check What MCP Changed

```
User: "What configuration has MCP created?"

AI calls: mcp_config_mcp_changes()

Returns: [
  {name: "node.type.article", operation: "create", timestamp: "..."},
  {name: "field.storage.node.field_image", operation: "create", ...},
  {name: "user.role.editor", operation: "create", ...}
]
```

### Export Configuration

```
User: "Export all the changes to Git"

AI calls: mcp_config_export()

Returns: {
  success: true,
  exported: 15,
  message: "Configuration exported to sites/default/files/config/sync"
}
```

### Preview Changes

```
User: "What would happen if I created an Event content type?"

AI calls: mcp_config_preview(
  operation: "create_content_type",
  parameters: {id: "event", label: "Event"}
)

Returns: {
  would_create: [
    "node.type.event",
    "core.entity_form_display.node.event.default",
    "core.entity_view_display.node.event.default"
  ],
  would_modify: []
}
```

### Show Config Diff

```
User: "Show me what's different in the article type config"

AI calls: mcp_config_diff(name: "node.type.article")

Returns: {
  name: "node.type.article",
  diff: "--- sync\n+++ active\n@@ -5,6 +5,7 @@\n+  new_field: value"
}
```

## Workflow Recommendation

After using MCP write tools:

```bash
# 1. Check what MCP created
drush mcp:changes

# 2. Export to sync directory
drush config:export

# 3. Commit to Git
git add config/
git commit -m "Export MCP-created configuration"
```

## Safety Features

- **Read-only by default:** Export requires explicit action
- **Change tracking:** All MCP changes tracked with timestamps
- **Preview mode:** See what would happen before making changes
- **Audit logging:** All config operations logged
