# MCP Tools - Batch

Bulk/batch operations for MCP Tools. Perform multiple content, user, taxonomy, and redirect operations in a single request.

## Tools (6)

| Tool | Description |
|------|-------------|
| `mcp_batch_create_content` | Create multiple nodes at once |
| `mcp_batch_update_content` | Update multiple nodes at once |
| `mcp_batch_delete_content` | Delete multiple nodes at once |
| `mcp_batch_publish` | Publish or unpublish multiple nodes |
| `mcp_batch_assign_role` | Assign a role to multiple users |
| `mcp_batch_create_terms` | Create multiple taxonomy terms |

## Requirements

- mcp_tools (base module)

Optional:
- redirect module (for batch redirect creation via service)

## Installation

```bash
drush en mcp_tools_batch
```

## Batch Limits

All batch operations are limited to **50 items maximum** per request to prevent timeouts and server overload. For larger operations, split your data into multiple batch calls.

## Example Usage

### Batch Create Content

```
User: "Create 5 article drafts with these titles"

AI calls: mcp_batch_create_content(
  content_type: "article",
  items: [
    { "title": "Article One", "fields": { "body": "Content for article one" } },
    { "title": "Article Two", "fields": { "body": "Content for article two" } },
    { "title": "Article Three" },
    { "title": "Article Four" },
    { "title": "Article Five", "status": true }
  ]
)
```

Response includes:
- `created_count`: Number successfully created
- `created`: Array of created nodes with nid, uuid, title, url
- `errors`: Any failures with details

### Batch Update Content

```
User: "Update the titles for nodes 10, 11, and 12"

AI calls: mcp_batch_update_content(
  updates: [
    { "id": 10, "fields": { "title": "New Title One" } },
    { "id": 11, "fields": { "title": "New Title Two", "body": "Updated body" } },
    { "id": 12, "fields": { "status": true } }
  ]
)
```

### Batch Delete Content

```
User: "Delete these draft articles"

AI calls: mcp_batch_delete_content(
  ids: [100, 101, 102, 103],
  force: false
)
```

Note: By default, only unpublished content is deleted. Set `force: true` to delete published content.

### Batch Publish/Unpublish

```
User: "Publish all these articles at once"

AI calls: mcp_batch_publish(
  ids: [10, 11, 12, 13, 14],
  publish: true
)
```

```
User: "Unpublish these outdated pages"

AI calls: mcp_batch_publish(
  ids: [20, 21, 22],
  publish: false
)
```

### Batch Assign Role

```
User: "Make these users editors"

AI calls: mcp_batch_assign_role(
  role: "editor",
  user_ids: [5, 6, 7, 8, 9]
)
```

Note: The "administrator" role cannot be assigned via batch operations for security reasons.

### Batch Create Terms

```
User: "Add these categories to the taxonomy"

AI calls: mcp_batch_create_terms(
  vocabulary: "categories",
  terms: [
    "Technology",
    "Science",
    { "name": "Health", "description": "Health-related topics" },
    { "name": "Sports", "weight": 10 },
    { "name": "Sub-category", "parent": 42 }
  ]
)
```

Terms can be simple strings (just the name) or objects with additional properties:
- `name` (required): Term name
- `description`: Optional description
- `parent`: Parent term ID for hierarchical terms
- `weight`: Sort weight

## Response Format

All batch operations return a consistent response format:

```json
{
  "success": true,
  "data": {
    "total_requested": 5,
    "created_count": 4,
    "skipped_count": 0,
    "error_count": 1,
    "created": [...],
    "skipped": [...],
    "errors": [
      {
        "index": 2,
        "error": "Description of what went wrong",
        "data": { ... }
      }
    ],
    "message": "Batch operation complete: 4 created, 0 skipped, 1 errors."
  }
}
```

## Safety Features

- **Batch limit**: Maximum 50 items per operation to prevent timeouts
- **Revisions**: Updates create new revisions preserving content history
- **Publish protection**: Delete operations skip published content unless `force: true`
- **Administrator protection**: Cannot assign administrator role via batch
- **User 1 protection**: Cannot modify the super admin user (uid 1)
- **Duplicate prevention**: Term creation skips existing terms
- **Access control**: All operations respect write access permissions
- **Audit logging**: All batch operations are logged for auditing

## Use Cases

1. **Content migration**: Bulk import content from external sources
2. **Mass updates**: Update multiple nodes with new field values
3. **Content cleanup**: Delete old drafts or unpublished content
4. **Launch day**: Publish multiple pieces of content simultaneously
5. **User management**: Assign roles to multiple users after onboarding
6. **Taxonomy setup**: Populate vocabularies with initial terms
7. **SEO redirects**: Create multiple redirects during site restructuring
