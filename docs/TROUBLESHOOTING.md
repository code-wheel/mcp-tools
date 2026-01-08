# Troubleshooting

Common issues and fixes for MCP Tools.

## Quick checks

- Run `drush mcp:server-smoke --server=default` to validate dependencies and profile config.
- Inspect the active profile with `drush mcp:server-info --server=default --tools --resources --prompts`.
- Validate component definitions with `drush mcp:components-validate`.

## Remote HTTP (mcp_tools_remote)

- **404 Not found**: remote transport is disabled or blocked by IP/Origin allowlists. Check `/admin/config/services/mcp-tools/remote`.
- **401 Authentication required**: missing or invalid API key. Send `Authorization: Bearer <token>` or `X-MCP-Api-Key: <token>`.
- **403 Access denied**: server profile access callback blocked the request, or scopes are disallowed for the profile.
- **406 Not acceptable**: the endpoint requires `Accept: application/json, text/event-stream` for POST and `Accept: text/event-stream` for GET.
- **500 Missing dependency**: install `mcp/sdk` via Composer for Streamable HTTP support.
- **Execution user errors**: set a valid uid in remote settings. If uid 1 is configured, the "Use site admin (uid 1)" checkbox must be enabled.

## STDIO (mcp_tools_stdio)

- Ensure `mcp_tools_stdio` is enabled.
- Run `drush mcp-tools:serve --uid=1` (or a dedicated MCP user) to avoid anonymous access.

## Tool list is smaller than expected

- **Gateway mode** only exposes `discover-tools`, `get-tool-info`, and `execute-tool`.
- **include_all_tools** is off by default; only Tool API plugins from providers starting with `mcp_tools` are listed.
- Ensure needed submodules are enabled and the execution user has the matching "Use MCP ..." permissions.

## Resources or prompts missing

- Confirm the server profile has `enable_resources` / `enable_prompts` enabled.
- If `component_public_only` is enabled, component definitions must include `public: TRUE` (or `mcp.public: TRUE`).

## Scope mismatches

- Scopes are intersected across global settings, server profile scopes, and (for HTTP) API key scopes.
- Use `mcp_tools.settings` and `mcp_tools_servers.settings` to confirm allowed scopes.

## Streamable HTTP session issues

- Streamable HTTP requires the `Mcp-Session-Id` header after initialize. Confirm your client supports MCP 2025-06-18.
