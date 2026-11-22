# MCP Tools - Policy for MCP Server

Experimental. For a site that serves tools through
[MCP Server](https://www.drupal.org/project/mcp_server) 2.x and its
[Tool Bridge](https://www.drupal.org/project/mcp_server_tool_bridge),
not through MCP Tools' own server.

## What it does

MCP Server runs a Tool API tool for any user who has the tool's
permission. This module adds the rules of MCP Tools for every Tool API
tool that is called on MCP Server's HTTP endpoint (`/mcp`), whichever
module the tool comes from:

- **Scopes and modes.** A write tool needs the `write` scope and a
  trigger tool the `admin` scope. A connection of MCP Server gets the
  default scopes of `mcp_tools.settings`. Read-only mode refuses every
  write. So does config-only mode, unless it allows every kind of write:
  nothing says what a tool of another module writes.
- **Write limits.** A write counts against the write limits, a trigger
  against the structure limit, a write that declares itself destructive
  against the delete limit.
- **Audit log.** A write or trigger call that ran has a line in the
  audit log, with the tool, its arguments (secrets taken out) and
  whether it worked.

A call that is refused never reaches MCP Server. The client gets a tool
result marked as an error whose text says why: "Access denied.", or the
limit and how many seconds to wait.

MCP Tools' own tools are not judged here. They check the same rules
themselves, also when MCP Server runs them.

## Setup

```bash
drush en mcp_tools_policy
```

There is nothing to configure. The settings are those of MCP Tools
(`/admin/config/services/mcp-tools`): default scopes, read-only mode,
rate limiting, audit logging.

Mind the default scopes. They are `read` on a fresh site, so with this
module on, write tools of other modules are refused on MCP Server until
you add `write`.

## How

It is a PSR-15 middleware tagged `mcp_server.transport_middleware`,
the place MCP Server documents for such rules. It runs before the MCP
SDK sees the request. How a call ended comes from the SDK's
`ResponseEvent` and `ErrorEvent`.

## What it does not do

- **MCP Server over STDIO.** There is no HTTP stack there, so none of
  this holds.
- **Tools that are not Tool API tools.** MCP Server can also have tools
  of its own kind (its `#[Tool]` plugins). This module only knows the
  ones the Tool Bridge exposes. The others run as before.
- **It cannot ask.** MCP Tools' own server asks the user of the client
  before a destructive tool. Here nobody can be asked. So on a site
  that insists on a yes (the setting "Strong calls need a
  confirmation", on for a site installed with 1.0.0-beta22 or later) a
  tool that declares itself destructive is refused. On a site that does
  not insist it runs.
- **It cannot see whether a failed call had run.** A call that comes
  back as an error keeps its count against the limit and gets an audit
  line that says it failed, also when Tool API refused it for its input
  or its access check. MCP Tools' own server knows the difference and
  gives the count back.
- **Several messages in one POST.** When one of them is a refused call,
  none of them runs, and each request in the POST gets an answer.
- **Other callers.** ECA, Drupal AI agents and code of the site call
  tools without MCP Server and are not covered.

## Tested against

MCP Server 2.0.0-beta7, MCP Server Tool Bridge 1.0.0-beta5, Tool API
1.0.0-beta12, mcp/sdk 0.9.0, with the official JS client over HTTP.
Both modules are in beta: a later release of theirs can change the
extension point this uses.
