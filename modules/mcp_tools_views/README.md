# MCP Tools - Views

Views creation and management for MCP Tools.

## Tools (6)

| Tool | Description |
|------|-------------|
| `mcp_views_create_view` | Create custom views |
| `mcp_views_create_content_list` | Quick content listing view |
| `mcp_views_delete_view` | Remove custom views |
| `mcp_views_add_display` | Add display to existing view |
| `mcp_views_enable` | Enable a view |
| `mcp_views_disable` | Disable a view |

## Requirements

- mcp_tools (base module)
- drupal:views

## Installation

```bash
drush en mcp_tools_views
```

## Example Usage

### Quick Content List

```
User: "Create a view showing recent articles"

AI calls: mcp_views_create_content_list(
  id: "recent_articles",
  label: "Recent Articles",
  content_type: "article",
  display_type: "page",
  path: "/articles",
  items_per_page: 10
)
```

### Custom View with Filters

```
User: "Create a view of published events sorted by date"

AI calls: mcp_views_create_view(
  id: "upcoming_events",
  label: "Upcoming Events",
  base_table: "node_field_data",
  displays: {
    page_1: {
      display_plugin: "page",
      display_options: {
        path: "events",
        filters: {
          type: {value: "event"},
          status: {value: 1}
        },
        sorts: {
          field_event_date: {order: "ASC"}
        }
      }
    }
  }
)
```

### Add Block Display

```
User: "Add a block display to the articles view"

AI calls: mcp_views_add_display(
  view_id: "recent_articles",
  display_type: "block",
  display_id: "sidebar_block",
  options: {
    items_per_page: 5
  }
)
```

## Safety Features

- **Core views protected:** Cannot delete views provided by Drupal core
- **Validation:** View configuration validated before save
- **Audit logging:** All view operations logged
