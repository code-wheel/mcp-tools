# Troubleshooting

Common issues and fixes for MCP Tools.

## Quick checks

- Run `drush mcp:server-smoke --server=default` to validate dependencies and profile config.
- Inspect the active profile with `drush mcp:server-info --server=default --tools --resources --prompts`.

## Remote HTTP (mcp_tools_remote)

- **404 Not found**: remote transport is disabled or blocked by IP/Origin allowlists. Check `/admin/config/services/mcp-tools/remote`.
- **401 Authentication required**: missing or invalid API key. Send `Authorization: Bearer <token>` or `X-MCP-Api-Key: <token>`.
- **403 Access denied**: server profile access callback blocked the request, or scopes are disallowed for the profile.
- **406 Not acceptable**: the endpoint requires `Accept: application/json, text/event-stream` for POST and `Accept: text/event-stream` for GET.
- **500 Missing dependency**: install `mcp/sdk` via Composer for Streamable HTTP support.
- **403 Forbidden: Invalid Host header**: mcp/sdk 0.3+ enables DNS-rebinding protection that only allows localhost by default. Add your site's public hostname (or full origin, e.g. `https://example.com`) to the Origin allowlist at `/admin/config/services/mcp-tools/remote`. The configured hostnames are passed to the middleware. Wildcard patterns (`*.example.com`) are matched by the module's own Origin check but not by the SDK host check, so list the exact endpoint hostname too.
- **Execution user errors**: set a valid uid in remote settings. If uid 1 is configured, the "Use site admin (uid 1)" checkbox must be enabled.

## STDIO (mcp_tools_stdio)

- Ensure `mcp_tools_stdio` is enabled.
- Run `drush mcp-tools:serve --uid=1` (or a dedicated MCP user) to avoid anonymous access.
- **Changes made elsewhere do not show**: the server is one long-running process. It reads content, config, State, permissions and field lists fresh on every call. A new language, a workflow put on a content type, or a newly installed module only show after the client restarts the server.

## Tool list is smaller than expected

- **Gateway mode** only exposes `mcp_tools_discover_tools`, `mcp_tools_get_tool_info`, and `mcp_tools_execute_tool`.
- **include_all_tools** is off by default; only Tool API plugins from providers starting with `mcp_tools` are listed.
- Ensure needed submodules are enabled and the execution user has the matching "Use MCP ..." permissions.

## Resources or prompts missing

- Confirm the server profile has `enable_resources` / `enable_prompts` enabled.

## Scope mismatches

- Scopes are intersected across global settings, server profile scopes, and (for HTTP) API key scopes.
- Use `mcp_tools.settings` and `mcp_tools.servers` to confirm allowed scopes.
- The recipe tools or other admin tools are missing over STDIO: the site does not allow the `admin` scope. A new site allows `read` and `write`. From 1.0.0-beta21 `--scope` and a server profile get no more than that, and the command says so on stderr. Tick "admin" under "Allowed scopes" on the settings form, or run `drush config:set mcp_tools.settings access.allowed_scopes '[read, write, admin]' --input-format=yaml -y`.
- The audit log is Drupal's log. Enable `dblog` or `syslog` to keep it. `dblog` trims old rows, so send the log off the server with `syslog` if you need it to last.

## Streamable HTTP session issues

- Streamable HTTP requires the `Mcp-Session-Id` header after initialize. Confirm your client supports MCP 2025-06-18.
