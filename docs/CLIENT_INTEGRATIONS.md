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

## Notes

- Claude Desktop, Claude Code, Cursor, and VS Code MCP integrations all accept
  the same `mcp.json` structure; only the config file location differs.
- Ensure the selected server profile allows the transport (`stdio` or `http`).
- For remote HTTP, create API keys with `drush mcp-tools:remote-key-create`.
- Prefer a dedicated execution user over uid 1 outside local development.
