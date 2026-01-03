# MCP Tools - Layout Builder

Layout Builder management for MCP Tools.

## Tools (9)

| Tool | Description |
|------|-------------|
| `mcp_layout_enable` | Enable Layout Builder for a content type |
| `mcp_layout_disable` | Disable Layout Builder |
| `mcp_layout_allow_custom` | Toggle per-entity layout overrides |
| `mcp_layout_get` | Get default layout sections |
| `mcp_layout_add_section` | Add a section to layout |
| `mcp_layout_remove_section` | Remove a section |
| `mcp_layout_add_block` | Add block to a section |
| `mcp_layout_remove_block` | Remove block from layout |
| `mcp_layout_list_plugins` | List available layout plugins |

## Requirements

- mcp_tools (base module)
- drupal:layout_builder

## Installation

```bash
drush en mcp_tools_layout_builder
```

## Example Usage

### Enable Layout Builder

```
User: "Enable Layout Builder for articles"

AI calls: mcp_layout_enable(
  entity_type: "node",
  bundle: "article"
)
```

### Allow Custom Layouts Per Node

```
User: "Let editors customize layouts on individual articles"

AI calls: mcp_layout_allow_custom(
  entity_type: "node",
  bundle: "article",
  allow: true
)
```

### Add Two-Column Section

```
User: "Add a two-column section to the article layout"

AI calls: mcp_layout_add_section(
  entity_type: "node",
  bundle: "article",
  layout_id: "layout_twocol",
  delta: 0
)
```

### Add Block to Section

```
User: "Add a related content block to the sidebar"

AI calls: mcp_layout_add_block(
  entity_type: "node",
  bundle: "article",
  section_delta: 0,
  region: "second",
  plugin_id: "views_block:related_content-block_1"
)
```

### List Available Layouts

```
User: "What layout options are available?"

AI calls: mcp_layout_list_plugins()

Returns: [
  {id: "layout_onecol", label: "One column"},
  {id: "layout_twocol", label: "Two column"},
  {id: "layout_twocol_bricks", label: "Two column bricks"},
  {id: "layout_threecol_25_50_25", label: "Three column 25/50/25"},
  ...
]
```

## Safety Features

- **Layout validation:** Layout plugins validated before use
- **Section ordering:** Sections added at correct delta positions
- **Audit logging:** All layout operations logged
