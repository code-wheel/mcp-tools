# Quickstart

A working MCP server in a few minutes, on a local site.

## 1) Enable the modules

```bash
composer require drupal/mcp_tools
drush en mcp_tools mcp_tools_stdio mcp_tools_content mcp_tools_structure -y
```

Add the submodules you need. The README lists them.

## 2) Check that the server starts

```bash
drush mcp:server-smoke --server=default
```

## 3) Connect a client

```bash
drush mcp-tools:client-config > .mcp.json
```

That writes a config most MCP clients read. It starts the server like
this:

```bash
drush mcp-tools:serve --quiet --uid=1 --scope=read,write
```

Use `--scope=read` for a connection that cannot change anything, and
add `--gateway` for a model on your own machine. For anything but local
development, use a user of its own and not user 1.

[CLIENT_INTEGRATIONS.md](CLIENT_INTEGRATIONS.md) has examples for
Claude Code, Codex CLI, HTTP and local models.

## Optional: the development preset

```bash
drush mcp:dev-profile
```

It enables the usual submodules, turns read-only mode off, allows all
three scopes (also `admin`), and switches the audit log and the write
limits off. For a local site only.

## Tools of your own

Write a Tool API plugin. The server lists the plugins of modules whose
name starts with `mcp_tools`, and every module's with `--all-tools`.
