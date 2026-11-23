# MCP Tools - Blocks

Block placement and configuration for MCP Tools.

## Tools (5)

| Tool | Description |
|------|-------------|
| `mcp_place_block` | Place a block in a region |
| `mcp_remove_block` | Remove a placed block |
| `mcp_configure_block` | Configure block settings |
| `mcp_list_available_blocks` | List available blocks |
| `mcp_list_regions` | List theme regions |

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

AI calls: mcp_place_block(
  plugin_id: "views_block:content_recent-block_1",
  region: "sidebar_first",
  theme: "olivero"
)
```

### List Available Regions

```
User: "What regions are available in the current theme?"

AI calls: mcp_list_regions(theme: "olivero")
```

### Configure Block Visibility

```
User: "Make the sidebar block only show on article pages"

AI calls: mcp_configure_block(
  block_id: "olivero_views_block__content_recent_block_1",
  visibility: {
    "entity_bundle:node": {
      bundles: ["article"]
    }
  }
)
```

Visibility is keyed by condition, the same for placing and configuring:
`request_path` (`pages`, one path a line or a list), `user_role`
(`roles`, a list) and `entity_bundle:node` (`bundles`, a list of content
types). The conditions given replace the ones the block had.

`settings` are the block's own settings, by name. A setting the block
does not have is refused, and the answer names the ones it has.

### Place System Blocks

```
User: "Add the site branding block to the header"

AI calls: mcp_place_block(
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
