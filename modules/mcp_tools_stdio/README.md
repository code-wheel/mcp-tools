# MCP Tools - STDIO Server

Expose MCP Tools over STDIO via Drush (recommended for local development).

## Overview

This module provides an MCP server that communicates over standard input/output (STDIO). This is the recommended transport for local development as it's secure (no network exposure) and works seamlessly with MCP clients like Claude Desktop and Claude Code.

## Requirements

- mcp_tools (base module)
- Drush

## Installation

```bash
drush en mcp_tools_stdio
```

## Usage

### Start the MCP Server

```bash
# Run as site admin (local dev)
drush mcp-tools:serve --uid=1

# With specific scopes
drush mcp-tools:serve --uid=1 --scope=read,write

# With a specific server profile
drush mcp-tools:serve --uid=1 --server=development

# In gateway mode (reduced tool list)
drush mcp-tools:serve --uid=1 --gateway
```

### Scope via Environment

```bash
MCP_SCOPE=read,write drush mcp-tools:serve --uid=1
```

## Client Configuration

### Claude Desktop

Add to `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "drupal": {
      "command": "drush",
      "args": ["mcp-tools:serve", "--uid=1"],
      "cwd": "/path/to/drupal"
    }
  }
}
```

### Claude Code

Add to your MCP settings:

```json
{
  "mcpServers": {
    "drupal": {
      "command": "drush",
      "args": ["mcp-tools:serve", "--uid=1"],
      "cwd": "/path/to/drupal"
    }
  }
}
```

### DDEV Integration

```json
{
  "mcpServers": {
    "drupal": {
      "command": "ddev",
      "args": ["drush", "mcp-tools:serve", "--uid=1"],
      "cwd": "/path/to/drupal"
    }
  }
}
```

## Gateway Mode

For clients with limited tool capacity, use `--gateway` to expose only 3 meta-tools:

```bash
drush mcp-tools:serve --uid=1 --gateway
```

This exposes:
- `mcp_tools/discover-tools` - List available tools
- `mcp_tools/get-tool-info` - Get tool details
- `mcp_tools/execute-tool` - Execute any tool by name

## Server Profiles

Use server profiles for different environments:

```bash
# List available profiles
drush mcp:servers

# Use a specific profile
drush mcp-tools:serve --uid=1 --server=production
```

Configure profiles in `config/sync/mcp_tools_servers.settings.yml`.

## Debugging

### Verbose Output

```bash
drush mcp-tools:serve --uid=1 -v
```

### Test Connection

```bash
# Send a ping request
echo '{"jsonrpc":"2.0","method":"ping","id":1}' | drush mcp-tools:serve --uid=1
```

## Security Notes

- STDIO transport is inherently secure (no network exposure)
- The `--uid` flag determines which Drupal user the tools run as
- For shared development environments, use a dedicated user instead of uid 1
- Scope restrictions still apply based on configuration
