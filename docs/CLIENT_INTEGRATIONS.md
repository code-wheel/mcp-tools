# Client Integrations

This pack provides ready MCP client snippets for both STDIO and HTTP. Most MCP
clients accept a `mcp.json` file with an `mcpServers` map. See
`mcp_tools/mcp.json.example` for a combined example.

## STDIO (local, recommended)

```json
{
  "mcpServers": {
    "drupal-stdio": {
      "command": "drush",
      "args": [
        "--root=/path/to/drupal",
        "mcp-tools:serve",
        "--server=development",
        "--uid=1"
      ],
      "env": {
        "MCP_SCOPE": "read,write"
      }
    }
  }
}
```

## HTTP (remote)

```json
{
  "mcpServers": {
    "drupal-http": {
      "type": "http",
      "url": "https://example.com/_mcp_tools",
      "headers": {
        "Authorization": "Bearer YOUR_API_KEY"
      }
    }
  }
}
```

## MCP Server 2.x (alternative transport)

To serve this tool library through
[MCP Server](https://www.drupal.org/project/mcp_server) instead of the
built-in transports:

```bash
composer require 'drupal/mcp_server:^2.0@alpha'
# The Tool Bridge has no tagged release yet and its composer package
# currently ships no code — clone the 1.x branch instead:
git clone https://git.drupalcode.org/project/mcp_server_tool_bridge.git \
  web/modules/contrib/mcp_server_tool_bridge
drush en mcp_server mcp_server_tool_bridge
```

1. Grant the `access mcp server` permission to the role your MCP client
   authenticates as.
2. Expose tools at `/admin/config/services/mcp-server/tools` — one
   config entity per MCP Tools tool you want available (the form
   autocompletes Tool API tool names). Nothing is exposed by default.
3. Point your MCP client at mcp_server's `/_mcp` endpoint.

MCP Tools' own protections still apply: scopes from
`mcp_tools.settings`, category permissions, protected entities, and the
delete confirmation guardrails. Known difference: the bridge currently
drops unknown tool arguments silently instead of rejecting them the way
MCP Tools' transports do. Compatibility with the bridge is re-verified
weekly by this project's Bridge Compat CI job.

## Notes

- Claude Desktop, Claude Code, Cursor, and VS Code MCP integrations all accept
  the same `mcp.json` structure; only the config file location differs.
- Ensure the selected server profile allows the transport (`stdio` or `http`).
- For remote HTTP, create API keys with `drush mcp-tools:remote-key-create`.
- Prefer a dedicated execution user over uid 1 outside local development.
