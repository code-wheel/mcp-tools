# MCP Tools - JSON:API

Generic entity CRUD operations via Drupal's JSON:API for AI agents.

## Overview

This module provides a fallback mechanism for AI agents to interact with any Drupal entity type that doesn't have dedicated MCP tools. It uses Drupal's JSON:API module to provide generic CRUD operations.

**Note:** Prefer curated, entity-specific tools (like `mcp_tools_content`, `mcp_tools_users`) when available. Use JSON:API tools as a fallback for entity types without dedicated tooling.

## Tools (6)

| Tool | Description |
|------|-------------|
| `mcp_jsonapi_discover_types` | Discover all accessible entity types and bundles |
| `mcp_jsonapi_get_entity` | Get a single entity by UUID |
| `mcp_jsonapi_list_entities` | List entities with optional filters and pagination |
| `mcp_jsonapi_create_entity` | Create a new entity |
| `mcp_jsonapi_update_entity` | Update an existing entity |
| `mcp_jsonapi_delete_entity` | Delete an entity |

## Requirements

- mcp_tools (base module)
- drupal:jsonapi

## Installation

```bash
drush en mcp_tools_jsonapi
```

## Configuration

Configure at `/admin/config/services/mcp-tools/jsonapi`:

- **Blocked entity types** - Entity types that should never be accessible (default: user, shortcut)
- **Allowed entity types** - Explicit allowlist (empty = all non-blocked types)
- **Allow write operations** - Enable/disable create/update/delete
- **Max items per page** - Pagination limit (default: 50)

## Example Usage

### Discover Entity Types

```
User: "What entity types are available on this site?"

AI calls: mcp_jsonapi_discover_types()
```

### Get Entity

```
User: "Get the taxonomy term with UUID abc-123"

AI calls: mcp_jsonapi_get_entity(
  entity_type: "taxonomy_term",
  uuid: "abc-123"
)
```

### List Entities

```
User: "Show me all published articles"

AI calls: mcp_jsonapi_list_entities(
  entity_type: "node",
  bundle: "article",
  filters: { "status": 1 },
  limit: 25
)
```

## Safety Features

- Blocks sensitive entity types by default (user, shortcut)
- Always blocks hardcoded sensitive types (key, oauth2_token, etc.)
- Respects Drupal entity access permissions
- Write operations require explicit `write` scope
- All operations are audit logged
