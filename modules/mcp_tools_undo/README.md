# MCP Tools - Undo

Undo what one MCP tool call changed.

## What it does

While this module is on, every tool call that changes something is kept as
an MCP tool call record, for 30 days by default (`keep_days` in
`mcp_tools_undo.settings`):

- content it made, changed or deleted, with the revision or the values
  before;
- config it saved or deleted, with the data before. Config entities (a
  content type, a field, a view) go through Drupal's own entity API.

`mcp_undo_call` undoes one call, newest change first:

- content the call made is deleted;
- content it changed goes back to the revision before, as a new revision,
  so the history stays (or gets its values back, when it has no
  revisions);
- content it deleted is made again;
- config gets its data back.

When anything the call changed was changed again since, by an editor or
another call, the undo is refused and nothing is undone. Later work is
never overwritten. The undo asks first, in clients that can ask.

`mcp_undo_recent` lists the recent calls that changed something, newest
first, with what each changed.

## For people

Reports > MCP tool calls (`/admin/reports/mcp-tool-calls`) lists the
calls: when, whose, what each changed, and whether it was undone. Each
one that can be undone has an Undo button, which asks first. Permissions:
"See what MCP tool calls changed" and "Undo MCP tool calls". The records
have Views data, for a view of your own.

## Who may undo

A call made by the same user: write scope. Another user's call: admin
scope. Each change counts against the write limits.

## What it does not undo

- Files on disk, and anything outside Drupal's entities and config.
- A call older than `keep_days`: old calls are removed on cron.
- Saves that were not made by a tool call. Only tool calls are written
  down.

## Tools

| Tool | What it does |
|---|---|
| `mcp_undo_recent` | The recent calls that changed something |
| `mcp_undo_call` | Undo one call (asks first) |
