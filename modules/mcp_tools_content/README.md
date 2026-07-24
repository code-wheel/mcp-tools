# MCP Tools - Content

Content CRUD operations for MCP Tools.

## Tools (4)

| Tool | Description |
|------|-------------|
| `mcp_create_content` | Create nodes with field values |
| `mcp_update_content` | Update existing content (creates revision); with `language`, updates only that translation (translatable fields + a `paragraphs` map keyed by source paragraph ID) |
| `mcp_delete_content` | Delete a single translation (`language`) or, with the explicit `confirm_delete_all` flag, the whole node with all translations. A bare `nid` is rejected. Unknown parameters are rejected |
| `mcp_publish_content` | Publish or unpublish content |

## Requirements

- mcp_tools (base module)
- drupal:node

## Installation

```bash
drush en mcp_tools_content
```

## Example Usage

### Create Content

```
User: "Create a new article titled 'Hello World' with some intro text"

AI calls: mcp_create_content(
  type: "article",
  title: "Hello World",
  body: "Welcome to our new blog!"
)
```

### Update Content

```
User: "Update article 42 to change the title"

AI calls: mcp_update_content(
  nid: 42,
  title: "Updated Title"
)
```

### Publish/Unpublish

```
User: "Unpublish the draft article"

AI calls: mcp_publish_content(
  nid: 42,
  status: false
)
```

## Safety Features

- Creates revisions on update (preserves history)
- Validates content type exists before creation
- Checks user permissions for content operations
- Audit logs all operations
