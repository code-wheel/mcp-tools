# MCP Tools - MCP Server Bridge

Optional bridge for exposing MCP Tools via the `drupal/mcp_server` module.

## Overview

This module provides compatibility with the [MCP Server](https://www.drupal.org/project/mcp_server) Drupal module. If you're using `mcp_server` as your MCP transport, this bridge generates tool configurations for all enabled MCP Tools.

**Note:** For most users, the built-in `mcp_tools_stdio` (local) or `mcp_tools_remote` (HTTP) transports are recommended. This bridge is only needed if you specifically want to use the upstream `drupal/mcp_server` module.

## Requirements

- mcp_tools (base module)
- mcp_server:mcp_server (contrib module)

## Installation

```bash
composer require drupal/mcp_server
drush en mcp_tools_mcp_server
```

## Usage

Sync MCP Tools to MCP Server configurations:

```bash
# Enable read-only tools
drush mcp-tools:mcp-server-sync --enable-read

# Enable read and write tools
drush mcp-tools:mcp-server-sync --enable-read --enable-write
```

## How It Works

1. Scans all enabled MCP Tools submodules
2. Converts Tool API plugin definitions to MCP Server tool configurations
3. Registers tools with MCP Server's tool registry

This allows MCP Server to expose MCP Tools through its own transport mechanism.

## When to Use

- You're already using `drupal/mcp_server` for other MCP tools
- You need MCP Server's specific features or transport options
- You want to consolidate all MCP tools under one server

## When NOT to Use

- For new projects, prefer `mcp_tools_stdio` (local dev) or `mcp_tools_remote` (HTTP)
- If you don't have a specific need for the upstream MCP Server module
