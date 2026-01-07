# MCP Tools - Theme

Theme management and settings for MCP Tools.

## Tools (8)

| Tool | Description |
|------|-------------|
| `mcp_theme_get_active` | Get current active theme info |
| `mcp_theme_list` | List all installed themes |
| `mcp_theme_set_default` | Set the default frontend theme |
| `mcp_theme_set_admin` | Set the admin theme |
| `mcp_theme_get_settings` | Get theme settings (logo, favicon, colors) |
| `mcp_theme_update_settings` | Update theme settings |
| `mcp_theme_enable` | Install/enable a theme |
| `mcp_theme_disable` | Uninstall a theme |

## Requirements

- mcp_tools (base module)

## Installation

```bash
drush en mcp_tools_theme
```

## Example Usage

### Switch Themes

```
User: "Switch to the Olivero theme"

AI calls:
1. mcp_theme_enable(theme: "olivero")
2. mcp_theme_set_default(theme: "olivero")
```

### Update Logo

```
User: "Change the site logo"

AI calls: mcp_theme_update_settings(
  theme: "olivero",
  settings: {
    logo: {
      use_default: false,
      path: "sites/default/files/logo.svg"
    }
  }
)
```

### Get Theme Info

```
User: "What theme is currently active?"

AI calls: mcp_theme_get_active()

Returns: {
  name: "olivero",
  label: "Olivero",
  version: "10.2.0",
  regions: ["header", "content", "sidebar_first", ...]
}
```

### Configure Theme Settings

```
User: "Hide the site slogan and use a custom favicon"

AI calls: mcp_theme_update_settings(
  theme: "olivero",
  settings: {
    features: {
      slogan: false
    },
    favicon: {
      use_default: false,
      path: "sites/default/files/favicon.ico"
    }
  }
)
```

## Safety Features

- **Active theme protected:** Cannot disable the current default theme
- **Admin theme protected:** Cannot disable the current admin theme
- **Theme validation:** Theme must exist and be compatible
- **Audit logging:** All theme operations logged
