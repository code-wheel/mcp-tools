# MCP Tools - Entity Clone

Entity Clone integration for MCP Tools. Provides tools for cloning Drupal entities with support for referenced entities and paragraph handling.

## Requirements

- MCP Tools (mcp_tools)
- Entity Clone module (entity_clone)

## Installation

1. Enable the Entity Clone module if not already enabled
2. Enable this module: `drush en mcp_tools_entity_clone`

## Available Tools

### mcp_entity_clone_clone

Clone a single entity with optional title modifications and child paragraph handling.

**Input:**
- `entity_type` (required): The entity type to clone (e.g., node, media, paragraph)
- `entity_id` (required): The ID of the entity to clone
- `title_prefix` (optional): Prefix to add to the cloned entity title
- `title_suffix` (optional): Suffix to add to the cloned entity title
- `clone_children` (optional): Whether to clone child paragraphs (default: true)

**Example - Basic clone:**
```json
{
  "entity_type": "node",
  "entity_id": "42"
}
```

**Example - Clone with custom title:**
```json
{
  "entity_type": "node",
  "entity_id": "42",
  "title_prefix": "[DRAFT] ",
  "title_suffix": " - Copy"
}
```

**Example - Clone without child paragraphs:**
```json
{
  "entity_type": "node",
  "entity_id": "42",
  "clone_children": false
}
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "entity_type": "node",
    "source_id": "42",
    "clone_id": "123",
    "clone_uuid": "abc123-def456-...",
    "label": "My Article (Clone)",
    "status": "unpublished",
    "message": "Successfully cloned node '42' to new entity '123'."
  }
}
```

### mcp_entity_clone_with_refs

Clone an entity along with specified referenced entities. Useful when you need to clone content that references other content that should also be unique to the clone.

**Input:**
- `entity_type` (required): The entity type to clone (e.g., node, media)
- `entity_id` (required): The ID of the entity to clone
- `reference_fields` (optional): List of reference field names to also clone

**Example - Clone article with related content:**
```json
{
  "entity_type": "node",
  "entity_id": "42",
  "reference_fields": ["field_related_articles", "field_featured_media"]
}
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "entity_type": "node",
    "source_id": "42",
    "clone_id": "123",
    "clone_uuid": "abc123-def456-...",
    "label": "My Article (Clone)",
    "cloned_references": [
      {
        "entity_type": "node",
        "source_id": "50",
        "clone_id": "124",
        "label": "Related Article 1"
      },
      {
        "entity_type": "media",
        "source_id": "10",
        "clone_id": "25",
        "label": "Featured Image"
      }
    ],
    "message": "Successfully cloned node with 2 referenced entities."
  }
}
```

### mcp_entity_clone_types

List all entity types that support cloning.

**Input:** None

**Example Response:**
```json
{
  "success": true,
  "data": {
    "types": [
      {
        "entity_type": "node",
        "label": "Content",
        "has_bundles": true,
        "bundles": [
          {"id": "article", "label": "Article"},
          {"id": "page", "label": "Basic page"}
        ]
      },
      {
        "entity_type": "media",
        "label": "Media",
        "has_bundles": true,
        "bundles": [
          {"id": "image", "label": "Image"},
          {"id": "document", "label": "Document"}
        ]
      }
    ],
    "total": 2
  }
}
```

### mcp_entity_clone_settings

Get clone settings and reference field information for a specific entity type and bundle.

**Input:**
- `entity_type` (required): The entity type (e.g., node, media)
- `bundle` (required): The bundle/content type machine name (e.g., article, page)

**Example:**
```json
{
  "entity_type": "node",
  "bundle": "article"
}
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "entity_type": "node",
    "bundle": "article",
    "settings": {
      "take_ownership": true,
      "no_suffix": false,
      "default_suffix": " (Clone)"
    },
    "reference_fields": [
      {
        "name": "field_related_articles",
        "label": "Related Articles",
        "target_type": "node",
        "cardinality": -1
      },
      {
        "name": "field_author",
        "label": "Author",
        "target_type": "user",
        "cardinality": 1
      }
    ],
    "paragraph_fields": [
      {
        "name": "field_content_blocks",
        "label": "Content Blocks",
        "target_type": "paragraph",
        "cardinality": -1
      }
    ],
    "has_paragraphs": true,
    "message": "Clone settings for node.article retrieved."
  }
}
```

## Supported Entity Types

The following entity types are supported for cloning:
- `node` - Content/nodes
- `media` - Media entities
- `paragraph` - Paragraph entities
- `taxonomy_term` - Taxonomy terms
- `block_content` - Custom blocks
- `menu_link_content` - Menu links

## Access Control

Write operations (clone) require:
- Write scope enabled for the MCP connection
- Site not in read-only mode

All clone operations are logged via the AuditLogger.

## Example Workflows

### Clone a landing page with all components

```
1. First, check the content structure:
   mcp_entity_clone_settings
   {"entity_type": "node", "bundle": "landing_page"}

2. Clone the landing page (paragraphs are cloned automatically):
   mcp_entity_clone_clone
   {
     "entity_type": "node",
     "entity_id": "100",
     "title_suffix": " - February Campaign"
   }
```

### Clone an article with its featured media

```
1. Clone with reference fields:
   mcp_entity_clone_with_refs
   {
     "entity_type": "node",
     "entity_id": "42",
     "reference_fields": ["field_featured_image", "field_gallery"]
   }
```

### Create a template from existing content

```
1. Clone and prefix as template:
   mcp_entity_clone_clone
   {
     "entity_type": "node",
     "entity_id": "50",
     "title_prefix": "[TEMPLATE] ",
     "title_suffix": ""
   }
```

## Paragraph Handling

When cloning nodes with paragraphs:

1. **Default behavior**: Child paragraphs are automatically cloned as new entities, ensuring the clone has its own independent paragraph instances.

2. **Nested paragraphs**: If paragraphs contain other paragraphs (nested structure), all levels are recursively cloned.

3. **Disable paragraph cloning**: Set `clone_children: false` to keep references to original paragraphs (use with caution as edits will affect both entities).

## Notes

- Cloned entities are created as unpublished by default
- The clone receives a "(Clone)" suffix unless custom prefix/suffix is specified
- Entity Clone module configuration is respected when available
- All operations create audit log entries for tracking
