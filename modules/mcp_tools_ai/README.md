# MCP Tools — AI Function Calls

Exposes MCP Tools' [Tool API](https://www.drupal.org/project/tool) tools to the
Drupal AI ecosystem ([AI](https://www.drupal.org/project/ai),
[AI Agents](https://www.drupal.org/project/ai_agents)) as native
**Function Calls** — so AI agents and assistants running *inside* your Drupal
site can use the same library that MCP clients use from outside.

No per-tool code: a deriver surfaces every Tool API tool as a
`drupal/ai` FunctionCall, and execution is delegated back to the tool (which
still enforces its own access check).

## Requirements

- `mcp_tools` (and any submodules whose tools you want exposed)
- `drupal/ai` (`ai`)
- `drupal/tool` (`tool`)

## Curated by default

AI agents pick tools more reliably from a focused set, and exposing write
operations to autonomous agents is risky. So by default only **read** and
**explain** operations are exposed (query-only). Enable more at
**Configuration → Web services → MCP Tools — AI Function Calls**
(`/admin/config/services/mcp-tools/ai`) or via config:

```yaml
# mcp_tools_ai.settings
exposed_operations:
  - read
  - explain
  - transform   # derive/reshape
  - trigger     # run an action (cron, cache clear, …)
  - write       # create/modify — opt-in, used with care
```

Tools become available to any `drupal/ai` consumer — for example the AI Agents
module — under the `mcp_tools` function-call group.

## How it works

- `Plugin/AiFunctionCall/Derivative/ToolApiFunctionCallDeriver` — emits one
  FunctionCall per Tool API tool whose operation is in `exposed_operations`,
  mapping the tool's typed inputs to context definitions.
- `Plugin/AiFunctionCall/ToolApiFunctionCall` — the adapter; on `execute()` it
  sets the tool's input values, runs it, and returns the result.
