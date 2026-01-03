# MCP Tools - Paragraphs

Drupal Paragraphs integration for MCP Tools. Provides tools for managing paragraph types and their fields.

## Requirements

- MCP Tools (mcp_tools)
- Paragraphs module (paragraphs)

## Installation

1. Enable the Paragraphs module if not already enabled
2. Enable this module: `drush en mcp_tools_paragraphs`

## Available Tools

### mcp_paragraphs_list_types

List all paragraph types with their fields.

**Input:** None

**Example Response:**
```json
{
  "success": true,
  "data": {
    "types": [
      {
        "id": "text_block",
        "label": "Text Block",
        "description": "A simple text paragraph",
        "field_count": 2,
        "fields": [
          {"name": "field_title", "type": "string", "label": "Title"},
          {"name": "field_body", "type": "text_long", "label": "Body"}
        ]
      }
    ],
    "total": 1
  }
}
```

### mcp_paragraphs_get_type

Get details of a specific paragraph type.

**Input:**
- `id` (required): Paragraph type machine name

**Example:**
```json
{"id": "text_block"}
```

### mcp_paragraphs_create_type

Create a new paragraph type.

**Input:**
- `id` (required): Machine name (lowercase, underscores)
- `label` (required): Human-readable name
- `description` (optional): Description text

**Example:**
```json
{
  "id": "image_gallery",
  "label": "Image Gallery",
  "description": "A gallery of images with captions"
}
```

### mcp_paragraphs_delete_type

Delete a paragraph type.

**Input:**
- `id` (required): Paragraph type machine name
- `force` (optional): Delete even if paragraphs exist (dangerous!)

**Example:**
```json
{"id": "unused_type"}
```

**With force:**
```json
{"id": "old_type", "force": true}
```

### mcp_paragraphs_add_field

Add a field to a paragraph type.

**Input:**
- `bundle` (required): Paragraph type machine name
- `field_name` (required): Field machine name (field_ prefix auto-added)
- `field_type` (required): Field type
- `label` (optional): Human-readable label
- `required` (optional): Whether field is required (default: false)
- `description` (optional): Help text
- `cardinality` (optional): 1 for single, -1 for unlimited (default: 1)
- `target_type` (optional): For entity_reference fields
- `target_bundles` (optional): Limit references to specific bundles
- `allowed_values` (optional): For list fields

**Available Field Types:**
- `string` - Plain text (255 chars max)
- `string_long` - Long plain text
- `text` - Formatted text (255 chars max)
- `text_long` - Long formatted text
- `text_with_summary` - Formatted text with summary
- `integer` - Integer number
- `decimal` - Decimal number
- `float` - Float number
- `boolean` - Checkbox
- `email` - Email address
- `link` - URL/Link
- `datetime` - Date and time
- `date` - Date only
- `image` - Image file
- `file` - Generic file
- `entity_reference` - Reference to another entity
- `list_string` - Select list (text keys)
- `list_integer` - Select list (integer keys)

**Example - Simple text field:**
```json
{
  "bundle": "text_block",
  "field_name": "subtitle",
  "field_type": "string",
  "label": "Subtitle"
}
```

**Example - Required long text:**
```json
{
  "bundle": "text_block",
  "field_name": "body",
  "field_type": "text_long",
  "label": "Body Text",
  "required": true,
  "description": "Enter the main content for this block"
}
```

**Example - Image field with unlimited values:**
```json
{
  "bundle": "image_gallery",
  "field_name": "images",
  "field_type": "image",
  "label": "Gallery Images",
  "cardinality": -1,
  "required": true
}
```

**Example - Entity reference to media:**
```json
{
  "bundle": "hero_banner",
  "field_name": "background",
  "field_type": "entity_reference",
  "label": "Background Image",
  "target_type": "media",
  "target_bundles": ["image"]
}
```

**Example - Select list:**
```json
{
  "bundle": "call_to_action",
  "field_name": "style",
  "field_type": "list_string",
  "label": "Button Style",
  "allowed_values": ["Primary", "Secondary", "Outline"]
}
```

### mcp_paragraphs_delete_field

Remove a field from a paragraph type.

**Input:**
- `bundle` (required): Paragraph type machine name
- `field_name` (required): Field machine name (with or without field_ prefix)

**Example:**
```json
{
  "bundle": "text_block",
  "field_name": "subtitle"
}
```

## Access Control

Write operations (create, delete, add/delete fields) require:
- Write scope enabled for the MCP connection
- Site not in read-only mode

All write operations are logged via the AuditLogger.

## Example Workflow

Create a complete "Hero Banner" paragraph type:

```
1. Create the paragraph type:
   mcp_paragraphs_create_type
   {"id": "hero_banner", "label": "Hero Banner", "description": "Full-width hero banner with image and text"}

2. Add a title field:
   mcp_paragraphs_add_field
   {"bundle": "hero_banner", "field_name": "title", "field_type": "string", "label": "Title", "required": true}

3. Add a subtitle field:
   mcp_paragraphs_add_field
   {"bundle": "hero_banner", "field_name": "subtitle", "field_type": "string", "label": "Subtitle"}

4. Add a background image field:
   mcp_paragraphs_add_field
   {"bundle": "hero_banner", "field_name": "background", "field_type": "image", "label": "Background Image", "required": true}

5. Add a CTA link field:
   mcp_paragraphs_add_field
   {"bundle": "hero_banner", "field_name": "cta_link", "field_type": "link", "label": "Call to Action"}

6. Verify the result:
   mcp_paragraphs_get_type
   {"id": "hero_banner"}
```

## Nested Paragraphs

To create nested paragraph structures (paragraphs containing other paragraphs), use an entity_reference field targeting paragraphs:

```json
{
  "bundle": "accordion",
  "field_name": "items",
  "field_type": "entity_reference",
  "label": "Accordion Items",
  "target_type": "paragraph",
  "target_bundles": ["accordion_item"],
  "cardinality": -1
}
```

Note: You'll need to use the entity_reference_revisions field type in production for proper paragraph behavior. This basic entity_reference works for simple cases.
