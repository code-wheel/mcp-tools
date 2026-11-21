# Quickstart

Get a working MCP server in a few minutes.

## 1) Optional: apply the dev profile

```bash
drush mcp:dev-profile
```

This applies the development preset and enables the recommended bundles.

## 2) Enable modules (if you skipped step 1)

```bash
drush en tool mcp_tools mcp_tools_stdio -y
```

## 3) Start the STDIO server

```bash
drush mcp-tools:serve --server=development --uid=1
```

For shared environments, use a dedicated execution user instead of uid 1.

## 4) Connect a client

See `mcp_tools/docs/CLIENT_INTEGRATIONS.md` for ready `mcp.json` examples.

## 5) Test a call

```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' | drush mcp-tools:serve --server=development --uid=1
```

To add tools of your own, write a Tool API plugin: the server lists the
plugins of modules whose name starts with `mcp_tools`, and every module's
with `--all-tools`.
