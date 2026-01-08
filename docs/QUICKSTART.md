# Quickstart

Get a working MCP server and your first custom tool in a few minutes.

## 1) Optional: apply the dev profile

```bash
drush mcp:dev-profile
```

This applies the development preset and enables the recommended bundles.

## 2) Enable modules (if you skipped step 1)

```bash
drush en tool mcp_tools mcp_tools_stdio -y
```

## 3) Scaffold a component module

```bash
drush mcp:scaffold --machine-name=mcp_quickstart --name="MCP Quickstart"
drush en mcp_quickstart -y
```

This creates a `mcp_quickstart/ping` tool that returns `pong`.

## 4) Start the STDIO server

```bash
drush mcp-tools:serve --server=development --uid=1
```

For shared environments, use a dedicated execution user instead of uid 1.

## 5) Connect a client

See `mcp_tools/docs/CLIENT_INTEGRATIONS.md` for ready `mcp.json` examples.

## 6) Test a call

```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' | drush mcp-tools:serve --server=development --uid=1
```

If you enable `component_public_only` in the server profile, ensure your
component definitions include `public: TRUE`.
