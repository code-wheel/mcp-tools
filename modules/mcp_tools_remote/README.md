# MCP Tools - Remote (HTTP)

Expose MCP Tools over HTTP with API key authentication.

## Overview

This module provides an HTTP endpoint for remote MCP clients to connect to your Drupal site. It's designed for scenarios where STDIO isn't available, such as Docker containers or remote development.

**Warning:** This is experimental and intended for trusted internal networks only. For local development, prefer `mcp_tools_stdio`.

## Requirements

- mcp_tools (base module)

## Installation

```bash
drush en mcp_tools_remote
```

## Configuration

Configure at `/admin/config/services/mcp-tools/remote`:

### Basic Settings

- **Enable HTTP endpoint** - Expose MCP at `/_mcp_tools`
- **Execution user** - User account for tool execution (use dedicated service account)
- **Server name/version** - Identification for MCP clients

### Security Settings

- **Allowed IPs** - IP/CIDR allowlist (e.g., `127.0.0.1`, `10.0.0.0/8`)
- **Allowed origins** - Origin/Host allowlist for DNS rebinding protection
- **Use uid 1** - Allow running as super admin (development only)

### API Keys

Manage keys via Drush:

```bash
# Quick setup with a default key
drush mcp-tools:remote-setup

# Create a key with specific scopes
drush mcp-tools:remote-key-create --label="My Key" --scopes=read --ttl=86400

# List all keys
drush mcp-tools:remote-key-list

# Revoke a key
drush mcp-tools:remote-key-revoke KEY_ID
```

## Client Configuration

### Claude Desktop / Claude Code

Add to your MCP client configuration:

```json
{
  "mcpServers": {
    "drupal": {
      "url": "https://your-site.local/_mcp_tools",
      "transport": "streamable-http",
      "headers": {
        "Authorization": "Bearer your-api-key"
      }
    }
  }
}
```

### Authentication

Send the API key via:
- `Authorization: Bearer <key>` (recommended)
- `X-MCP-Api-Key: <key>` (alternative)

## Gateway Mode

For clients with limited tool capacity, enable Gateway mode to expose only 3 meta-tools:

- `mcp_tools/discover-tools` - List available tools
- `mcp_tools/get-tool-info` - Get tool details
- `mcp_tools/execute-tool` - Execute any tool by name

This reduces the initial tool list but still allows access to all tools.

## Security Recommendations

1. **Use IP allowlisting** - Restrict to trusted networks
2. **Use Origin allowlisting** - Prevent DNS rebinding attacks
3. **Use short-lived keys** - Set TTL for automatic expiration
4. **Use read-only keys** - Only grant write scope when necessary
5. **Use dedicated user** - Create `mcp_executor` account (button in UI)
6. **Monitor audit logs** - Watch for unusual activity

## Execution User

For production, create a dedicated service account:

1. Click "Create MCP Executor Account" in the settings form
2. This creates a `mcp_executor` user with the `mcp_executor` role
3. The role is granted only read-oriented MCP permissions by default
4. Add write permissions manually if needed

**Never use uid 1 in production.**
