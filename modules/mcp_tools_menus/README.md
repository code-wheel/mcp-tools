# MCP Tools - Menus

Menu and menu link management for MCP Tools.

## Tools (5)

| Tool | Description |
|------|-------------|
| `mcp_menus_create_menu` | Create new menus |
| `mcp_menus_delete_menu` | Remove custom menus |
| `mcp_menus_add_menu_link` | Add links to menus |
| `mcp_menus_update_menu_link` | Update menu link properties |
| `mcp_menus_delete_menu_link` | Remove menu links |

A link to a path of this site where nothing answers (no page, no path
alias, no file) is still made, because the page may come later. The
answer then has a `warning` that says so, and how to find the content
that was meant.

## Requirements

- mcp_tools (base module)
- drupal:menu_ui

## Installation

```bash
drush en mcp_tools_menus
```

## Example Usage

### Create a Menu with Links

```
User: "Create a footer menu with About, Contact, and Privacy links"

AI calls:
1. mcp_menus_create_menu(id: "footer", label: "Footer Menu")
2. mcp_menus_add_menu_link(menu: "footer", title: "About Us", uri: "internal:/about")
3. mcp_menus_add_menu_link(menu: "footer", title: "Contact", uri: "internal:/contact")
4. mcp_menus_add_menu_link(menu: "footer", title: "Privacy Policy", uri: "internal:/privacy")
```

### Add Links to Main Menu

```
User: "Add a Services link to the main menu"

AI calls: mcp_menus_add_menu_link(
  menu: "main",
  title: "Services",
  uri: "internal:/services",
  weight: 5
)
```

### Create Hierarchical Menu

```
User: "Add Products menu with sub-items"

AI calls:
1. mcp_menus_add_menu_link(menu: "main", title: "Products", uri: "internal:/products")
2. mcp_menus_add_menu_link(menu: "main", title: "Software", uri: "internal:/products/software", parent: "menu_link_content:uuid-of-products")
3. mcp_menus_add_menu_link(menu: "main", title: "Hardware", uri: "internal:/products/hardware", parent: "menu_link_content:uuid-of-products")
```

## Safety Features

- **System menus protected:** Cannot delete `admin`, `main`, `footer`, `tools`, `account`
- **Link validation:** URIs validated before creation
- **Audit logging:** All menu operations logged
