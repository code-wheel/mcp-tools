# Client Integrations

MCP is an open protocol, so any MCP client can connect, with any model
that can call tools. This page has ready snippets for STDIO and HTTP,
and what we measured with the clients and models we tried. Most clients
read a `mcp.json` file with an `mcpServers` map. `mcp.json.example` in
the module folder is one, and `drush mcp-tools:client-config` writes one
for your site.

## STDIO (local, recommended)

```json
{
  "mcpServers": {
    "drupal-stdio": {
      "command": "drush",
      "args": [
        "--root=/path/to/drupal",
        "mcp-tools:serve",
        "--quiet",
        "--uid=1",
        "--scope=read,write"
      ]
    }
  }
}
```

Use `--scope=read` for a connection that cannot change anything. A
server profile (`--server=staging`) can set the scopes and gateway mode
for you. No connection gets more scopes than the site allows: see
"Scopes" in the README.

## Codex CLI

Codex keeps its MCP servers in its own config, not in `mcp.json`:

```bash
codex mcp add drupal -- /path/to/drupal/vendor/bin/drush --root=/path/to/drupal mcp-tools:serve --quiet --uid=1 --scope=read,write
```

Tried with Codex CLI 0.166.0: it lists the tools, reads and writes.
Codex asks you before it runs a tool that can change the site. Where
there is nobody to ask (`codex exec`), it refuses such a call unless you
start it with `--approve-for-me`. In gateway mode a read-only connection
(`--scope=read`) needs no approval. With the write scope every call goes
through one tool that can also write, so Codex asks for each call.

## A model on your own machine

The model does not have to be in the cloud. Start the server in gateway
mode, so the model sees three tools (find, describe, run) and not the
whole list, which fits a small context:

```bash
drush mcp-tools:serve --quiet --uid=1 --scope=read,write --gateway
```

What we measured: twelve site tasks (make a content type, add a field,
create and unpublish an item, a role, a user, a menu link, a view, a
redirect and more), each checked on the site itself and not by what the
model said. Everything ran with 1.0.0-beta20 under Ollama 0.40.17 on one
graphics card (an RTX 5090), on 2026-11-21. The numbers are tasks done
out of twelve, one number per run.

| Model | Our small test client | Claude Code as the client |
|---|---|---|
| `gpt-oss:20b` | 12 in nine runs of ten, 11 in one | 12 in five runs of six, 11 in one |
| `qwen3.8:27b` | 12, 12, 12, 12 | 12, 12, 12 |
| `qwen3.6:27b` | 12, 12, 12, 12 | 12, 12, 12 |
| `muse-glimmer:30b` | 11, 11, 11, 12 | 10, 12, 12 |
| `mimo-v2.6:9b` (q4_K_M) | 11, 11, 11, 12 | 12, 12, 12 |

Three or four runs are a small sample. Read the table as "these models
can do it", not as a ranking. Where a task was missed, the model
slipped: it created an item unpublished, asked a question back and did
nothing, wrote a tool call out as text, or stopped with an empty answer.
A smaller model, `qwen3:8b`,
did five or six of the twelve when we tried it with 1.0.0-beta19, so
size still matters below a point.

Claude Code can be the client because Ollama speaks its message format.
No cloud model is involved:

```bash
ANTHROPIC_BASE_URL=http://localhost:11434 ANTHROPIC_AUTH_TOKEN=ollama \
  claude --model gpt-oss:20b
```

Other clients, with `gpt-oss:20b`, three runs each, first with
1.0.0-beta20 and then with 1.0.0-beta21: fast-agent did 11, 12, 11 and
then 12, 12, 12. Goose did 9, 11, 12 and then 12, 12, 11. OpenCode did
12, 12, 12 and then 12, 8, 12. In its bad run the model called our tools
in a way OpenCode does not offer and then answered from guesses. That
happens with either version, and three runs do not tell them apart. We
drive each client from a script without a person at the keyboard, so
your results by hand may differ.

Inside the site, with Drupal AI and our `mcp_tools_ai` bridge
(experimental): `gpt-oss:20b` did 11, 12 and 12 with 1.0.0-beta21, given
the 72 tools of seven of our modules. All 228 do not fit a local model's
context that way. The one miss was an error of Drupal AI itself, when
the model called a function it had not been offered.

1.0.0-beta21 answers a made-up tool name with the closest real ones,
names each found tool's inputs and puts the best match first. With it
`gpt-oss:20b` did the same twelve tasks in all ten runs with about a
fifth fewer tool calls (41 a run, down from 52). With Claude Code as
the client it did 12, 12 and 12. The other four models have not run on
1.0.0-beta21 yet. The 1.0.0-beta21 runs on this page were made before
its last security changes and were not repeated after them.

1.0.0-beta22 runs a tool whose name has only `tools_` left out. With
`gpt-oss:20b`, ten runs each way, the failed tool calls went from 9 to
4. The tasks done stayed at 119 of 120 and the number of tool calls did
not change. It also says in the server's instructions where the Drupal
version is: asked for it, the model had a failed call in 8 of 40 runs
before and in none of 40 after.

1.0.0-beta22 was also run with seven local models, four runs each, our
small test client, gateway mode, Ollama 0.40.17 on one RTX 5090. Tasks
done out of twelve, one number per run: `qwen3.8:27b` 12, 12, 12, 12;
`qwen3.6:27b` 12, 12, 12, 12; `gemma4:31b` 12, 12, 12, 12;
`flint:40b` 11, 12, 12, 10; `mimo-v2.6:9b` 12, 11, 11, 11;
`muse-glimmer:30b` 8, 10, 12, 8; `nemotron-3.5-lightning:30b` 8, 9, 9, 9.
Four runs are a small sample. `muse-glimmer` sometimes wrote its tool
call out as text, which Ollama did not turn into a call. `mimo` and
`nemotron` sent lists and objects as JSON text, which 1.0.0-beta23
reads as what they are.

On a harder set of twelve tasks, on a site with 24 content types and 33
items, `gpt-oss:20b` did 100 of 120 with 1.0.0-beta22 and 118 of 120
with 1.0.0-beta23, with a fifth fewer tool calls (ten runs each).

Most tasks a local model missed in these runs were not about the tools:
Ollama answered with an error because the model's own tool call was cut
off. A client that tells the model and lets it try again loses fewer.

Three things to know before you judge a model:

- The Ollama version matters. The same model scored lower after an Ollama
  upgrade, until 1.0.0-beta20 made gateway mode more forgiving. Try your
  own tasks.
- A small model often calls a tool by the name it found, and not through
  the run tool. From 1.0.0-beta20 that works.
- A new site (1.0.0-beta22 or later) runs a tool that destroys something
  only after the user of the client said yes. A client that cannot ask
  its user, such as one started from a script, is refused. See "Calls
  that ask first" in the README for how to change that.
- `ollama ps` has to say "100% GPU". When ours ran on the processor after
  a reboot, its context was also down to 4,096 tokens, which is too small
  for any of this.

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
composer require 'drupal/mcp_server:^2.0@beta' 'drupal/mcp_server_tool_bridge:^1.0@beta'
drush en mcp_server mcp_server_tool_bridge
```

1. Grant the `access mcp server` permission to the role your MCP client
   authenticates as.
2. Expose tools at `/admin/config/services/mcp-server/tools`: one
   config entity per MCP Tools tool you want available (the form
   autocompletes Tool API tool names). Nothing is exposed by default.
3. Point your MCP client at mcp_server's `/mcp` endpoint.

MCP Tools' own protections still apply to MCP Tools' own tools: scopes
from `mcp_tools.settings` (a connection of MCP Server gets the default
scopes), category permissions, protected entities, and the confirm flag
of the delete tool. Compatibility with the bridge is re-verified
weekly by this project's Bridge Compat CI job.

Nobody can be asked for a yes on this way in. With `mcp_tools_policy`
enabled (below), a site that insists on a yes refuses a destructive
tool here. Without it such a tool runs.

### The same rules for other modules' tools

The bridge can expose any Tool API tool, not only ours. A tool of another
module knows nothing of read-only mode, the scopes or the write limits,
and nothing writes an audit line for it. The optional submodule
`mcp_tools_policy` holds such tools to the same rules on MCP Server's
HTTP endpoint:

```bash
drush en mcp_tools_policy
```

- A write tool needs the `write` scope in the default scopes, and is
  refused in read-only mode. The client gets a tool result that says
  "Access denied".
- A write counts against the write limits. The call over the limit is
  answered with how many seconds to wait.
- A write that ran has a line in the audit log.
- A tool that declares itself destructive, of any module, is refused on
  a site that insists on a yes (the setting "Strong calls need a
  confirmation", on for a new site). Nobody can be asked here.

It does not cover MCP Server over STDIO. See the submodule's README for
what else it cannot see.

## Notes

- Claude Desktop, Claude Code, Cursor, and VS Code MCP integrations all accept
  the same `mcp.json` structure; only the config file location differs.
- Ensure the selected server profile allows the transport (`stdio` or `http`).
- For remote HTTP, create API keys with `drush mcp-tools:remote-key-create`.
- Prefer a dedicated execution user over uid 1 outside local development.
