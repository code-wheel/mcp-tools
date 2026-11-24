# MCP Tools - Migration

Import content, terms and redirects, and export content, on Drupal's
Migrate API.

## What the import does

- Each import is a migration (migrate_plus), named by its entity type,
  bundle and key. Run the same import again and it updates what it made.
  It makes no copies.
- `dry_run` says what each row would do and writes nothing.
- Up to 1000 rows an import, 100 a call. The answer has `remaining` and
  `done`: call again with the same rows and key until `done` is true.
- Every entity is checked by Drupal's validation before it is saved. A row
  that fails is reported with why, and the other rows go on.
- A column the type does not have refuses the import, with the fields
  there are.
- Reference fields take ids or names, several with `|`.
- `mcp_migration_rollback` undoes one import: it deletes what that import
  made, and keeps what it only updated.
- `drush migrate:import <import_id>` (migrate_tools) runs the same import.

## Installation

```bash
composer require drupal/migrate_plus
drush en mcp_tools_migration
```

It needs migrate and migrate_plus. migrate_tools is optional, for Drush.

## Tools

| Tool | What it does |
|---|---|
| `mcp_migration_import_json` | Import rows from a JSON list |
| `mcp_migration_import_csv` | Import rows from CSV, the first line the column names |
| `mcp_migration_import_status` | What an import made and what failed; without `import_id`, every import |
| `mcp_migration_rollback` | Undo one import (asks first) |
| `mcp_migration_validate` | Deprecated: use an import with `dry_run`, which runs the import's own checks |
| `mcp_migration_field_mapping` | The required and optional fields of a content type |
| `mcp_migration_export_json` | Export content, terms or redirects in id order, with `offset`, as rows the import reads back |
| `mcp_migration_export_csv` | The same as CSV |

## The key

A key is the column whose value names a row for good. A later import with
the same key updates the row's entity.

| Entity type | Key | When the key matches something the import did not make |
|---|---|---|
| `node` (the default) | Required: an id from the source, or `uuid` | With `uuid` it is updated: an export imports back as updates |
| `taxonomy_term` | `name` when left out | The term is left as it is, and listed under `existing` |
| `redirect` | `source` when left out | The redirect is left as it is, and listed under `existing` |

## Content

```json
{
  "tool": "mcp_migration_import_json",
  "input": {
    "content_type": "article",
    "key": "id",
    "items": [
      {"id": "a1", "title": "Article 1", "body": "Content 1", "field_tags": "News|Sport", "status": 1},
      {"id": "a2", "title": "Article 2", "body": "Content 2"}
    ]
  }
}
```

A new node is unpublished unless the row has `status`. The owner, ids and
dates are never taken from a row; they are listed in `ignored_columns`.

## Terms

`content_type` is the vocabulary. `parent` may name another row, in any
order, so a whole tree goes in one call:

```json
{
  "tool": "mcp_migration_import_json",
  "input": {
    "entity_type": "taxonomy_term",
    "content_type": "food",
    "items": [
      {"name": "Apple", "parent": "Fruit"},
      {"name": "Fruit"},
      {"name": "Gala", "parent": "Apple"}
    ]
  }
}
```

## Redirects

```json
{
  "tool": "mcp_migration_import_json",
  "input": {
    "entity_type": "redirect",
    "items": [
      {"source": "/old-page", "destination": "/node/12"},
      {"source": "/old-news", "destination": "/news", "status_code": 302}
    ]
  }
}
```

A destination that leads nowhere is made and said, under `warnings`.

## Export

The exports give the rows the import of the same type reads back:
`entity_type` (node, taxonomy_term, redirect) as for the import, and
`references: "names"` for tags and a term's parent by name, several joined
by `|`. Ids mean nothing on another site; names do. So an export from one
site imports into another:

```json
{"tool": "mcp_migration_export_json", "input": {"entity_type": "taxonomy_term", "content_type": "tags", "references": "names"}}
```

Then give its `items` to `mcp_migration_import_json` with the same
`entity_type`. Content keeps its `uuid`, so with `key: "uuid"` a second
import updates instead of making copies.

For one item or a filtered list, the JSON:API tools (`mcp_jsonapi_list_entities`,
`mcp_jsonapi_get_entity`) are the quicker way.

## CSV

Values with commas, quotes or line breaks go in double quotes, and a
double quote inside one is written `""`. `field_mapping` maps column names
to fields, like `{"Name": "title"}`. A row with too few or too many
columns is reported under `failed`.

## Field values

| Field type | Simple value | Full value |
|---|---|---|
| `text_long` | `"content"` | `{"value": "content", "format": "basic_html"}` |
| `entity_reference` | `123`, `"News"`, `"News\|Sport"` | `{"target_id": 123}` |
| `link` | `"https://example.com"` or `"/node/1"` | `{"uri": "https://...", "title": "Link"}` |
| `datetime` | `"2024-01-15"` | `{"value": "2024-01-15T10:00:00"}` |
| `boolean` | `true` or `1` | - |
| `integer` | `42` | - |

## Security

Imports need write access through the MCP Tools access manager, and each
row counts against the write limits. An undo counts each entity it
deletes. Every import is logged by the audit logger.
