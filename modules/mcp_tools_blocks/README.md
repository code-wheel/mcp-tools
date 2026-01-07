# MCP Tools - Blocks

Block placement and configuration for MCP Tools.

## Tools (5)

| Tool | Description |
|------|-------------|
| `mcp_blocks_place` | Place a block in a region |
| `mcp_blocks_remove` | Remove a placed block |
| `mcp_blocks_configure` | Configure block settings |
| `mcp_blocks_list_available` | List available blocks |
| `mcp_blocks_list_regions` | List theme regions |

## Requirements

- mcp_tools (base module)
- drupal:block

## Installation

```bash
drush en mcp_tools_blocks
```

## Example Usage

### Place a Block in Sidebar

```
User: "Add the recent content block to the sidebar"

AI calls: mcp_blocks_place(
  plugin_id: "views_block:content_recent-block_1",
  region: "sidebar_first",
  theme: "olivero"
)
```

### List Available Regions

```
User: "What regions are available in the current theme?"

AI calls: mcp_blocks_list_regions(theme: "olivero")
```

### Configure Block Visibility

```
User: "Make the sidebar block only show on article pages"

AI calls: mcp_blocks_configure(
  block_id: "olivero_views_block__content_recent_block_1",
  settings: {
    visibility: {
      node_type: {
        bundles: ["article"]
      }
    }
  }
)
```

### Place System Blocks

```
User: "Add the site branding block to the header"

AI calls: mcp_blocks_place(
  plugin_id: "system_branding_block",
  region: "header",
  theme: "olivero",
  settings: {
    use_site_logo: true,
    use_site_name: true
  }
)
```

## Safety Features

- **Theme validation:** Blocks only placed in valid theme regions
- **Plugin validation:** Block plugin must exist
- **Audit logging:** All block operations logged
