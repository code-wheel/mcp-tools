# MCP Tools - Migration

Content import/export and migration assistance tools for Drupal.

## Features

- Import content from CSV or JSON formats
- Export content to CSV or JSON formats
- Validate import data before processing
- Get field mapping information for content types
- Track import operation status

## Installation

Enable the module:

```bash
drush en mcp_tools_migration
```

## Available Tools

| Tool ID | Description |
|---------|-------------|
| `mcp_migration_import_csv` | Import content from CSV data |
| `mcp_migration_import_json` | Import content from JSON array |
| `mcp_migration_validate` | Validate data before import |
| `mcp_migration_field_mapping` | Get required/optional fields for a content type |
| `mcp_migration_export_csv` | Export content to CSV format |
| `mcp_migration_export_json` | Export content to JSON format |
| `mcp_migration_import_status` | Get status of last import operation |

## Limits

- **Maximum 100 items per import call** - Split larger imports into batches
- **Maximum 100 items per export call** - Use pagination for larger exports

## CSV Format Specification

### Structure

- First row must contain column headers
- Subsequent rows contain data
- Values containing commas, quotes, or newlines should be enclosed in double quotes
- Double quotes within values should be escaped as `""`

### Required Columns

- `title` (or `name`) - The content title

### Example CSV

```csv
title,body,field_category,status
"My First Article","This is the body content.",1,1
"Second Article","Another article body.",2,0
"Article with ""quotes""","Content with special, characters.",1,1
```

### Field Mapping

You can map CSV column names to Drupal field names:

```json
{
  "Name": "title",
  "Description": "body",
  "Category ID": "field_category"
}
```

## JSON Format Specification

### Structure

Array of objects, each representing a content item.

### Required Fields

- `title` (or `name`) - The content title

### Optional Fields

- `status` - Publish status (1 = published, 0 = draft)
- `field_*` - Any custom field values

### Example JSON

```json
[
  {
    "title": "My First Article",
    "body": "This is the body content.",
    "field_category": 1,
    "status": 1
  },
  {
    "title": "Second Article",
    "body": "Another article body.",
    "field_category": 2,
    "status": 0
  }
]
```

### Field Value Formats

Different field types accept different value formats:

| Field Type | Simple Value | Full Format |
|------------|--------------|-------------|
| `text_long` | `"content"` | `{"value": "content", "format": "basic_html"}` |
| `entity_reference` | `123` | `{"target_id": 123}` |
| `link` | `"https://example.com"` | `{"uri": "https://...", "title": "Link"}` |
| `datetime` | `"2024-01-15"` | `{"value": "2024-01-15T10:00:00"}` |
| `boolean` | `true` or `1` | - |
| `integer` | `42` | - |

## Usage Examples

### 1. Get Field Mapping

Before importing, check what fields are available:

```json
{
  "tool": "mcp_migration_field_mapping",
  "input": {
    "content_type": "article"
  }
}
```

Response:

```json
{
  "success": true,
  "data": {
    "content_type": "article",
    "label": "Article",
    "required": {
      "title": {"label": "Title", "type": "string"}
    },
    "optional": {
      "body": {"label": "Body", "type": "text_with_summary"},
      "field_tags": {"label": "Tags", "type": "entity_reference"}
    }
  }
}
```

### 2. Validate Import Data

Check data before importing:

```json
{
  "tool": "mcp_migration_validate",
  "input": {
    "content_type": "article",
    "items": [
      {"title": "Test Article", "body": "Content here"},
      {"body": "Missing title"}
    ]
  }
}
```

Response:

```json
{
  "success": true,
  "data": {
    "valid": false,
    "total_items": 2,
    "error_count": 1,
    "errors": [
      {"row": 2, "field": "title", "message": "Title (or name) is required."}
    ]
  }
}
```

### 3. Import from JSON

```json
{
  "tool": "mcp_migration_import_json",
  "input": {
    "content_type": "article",
    "items": [
      {"title": "Article 1", "body": "Content 1", "status": 1},
      {"title": "Article 2", "body": "Content 2", "status": 0}
    ]
  }
}
```

Response:

```json
{
  "success": true,
  "data": {
    "import_id": "import_abc123",
    "content_type": "article",
    "total_items": 2,
    "created_count": 2,
    "failed_count": 0,
    "created": [
      {"nid": 10, "title": "Article 1", "row": 1},
      {"nid": 11, "title": "Article 2", "row": 2}
    ],
    "message": "Successfully imported 2 of 2 items."
  }
}
```

### 4. Import from CSV

```json
{
  "tool": "mcp_migration_import_csv",
  "input": {
    "content_type": "article",
    "csv_data": "title,body,status\n\"First Article\",\"Body content\",1\n\"Second Article\",\"More content\",0",
    "field_mapping": {}
  }
}
```

### 5. Export to JSON

```json
{
  "tool": "mcp_migration_export_json",
  "input": {
    "content_type": "article",
    "limit": 50
  }
}
```

### 6. Export to CSV

```json
{
  "tool": "mcp_migration_export_csv",
  "input": {
    "content_type": "article",
    "limit": 50
  }
}
```

### 7. Check Import Status

```json
{
  "tool": "mcp_migration_import_status",
  "input": {}
}
```

## Security

Import operations require write access through the MCP Tools Access Manager. All import operations are logged through the Audit Logger for tracking and compliance.

## Best Practices

1. **Always validate first** - Use `mcp_migration_validate` before importing to catch errors early
2. **Use field mapping** - Check `mcp_migration_field_mapping` to understand required fields
3. **Batch large imports** - Split imports over 100 items into multiple calls
4. **Export before modify** - Use export tools to backup content before bulk changes
5. **Check status** - Use `mcp_migration_import_status` to verify import completion
