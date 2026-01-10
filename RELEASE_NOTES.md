# MCP Tools 1.0.0-beta1 Release Notes

## Short Summary (for Drupal.org release)

MCP Tools beta1 brings full PHP MCP ecosystem integration with 5 standalone Composer packages, 222 tools across 34 submodules, and 741 passing tests on Drupal 11. This release standardizes error handling with the McpError fluent builder and introduces DrupalToolProvider for consistent tool discovery patterns.

---

## Full Release Notes

### Highlights

**First Beta Release** - MCP Tools is now feature-complete for the 1.0 release. This beta focuses on stability, testing, and ecosystem integration.

**222 Tools** - Up from 154 in alpha, covering site building, content management, views, layout, caching, cron, and 20+ contrib module integrations.

**Full Test Coverage** - 741 unit tests passing on Drupal 11 with PHP 8.4, plus kernel and functional tests.

### New Features

#### DrupalToolProvider
New adapter class implementing `ToolProviderInterface` from mcp-tool-gateway. This provides a standardized interface for tool discovery and execution that aligns with the broader PHP MCP ecosystem.

#### MCP PHP Ecosystem Packages
MCP Tools now leverages 5 standalone Composer packages that can be used independently:

| Package | Version | Purpose |
|---------|---------|---------|
| mcp-error-codes | ^1.2 | ErrorCode constants + McpError fluent builder |
| mcp-schema-builder | ^1.1 | TypeMapper, SchemaValidator, McpSchema presets |
| mcp-tool-gateway | ^1.1 | ToolProviderInterface, middleware pipeline |
| mcp-http-security | ^1.0 | API key validation, IP allowlist, scopes |
| mcp-events | ^2.0 | Tool execution events |

### Changes

#### Error Handling
- `DefaultToolErrorHandler` now uses `McpError` fluent builder
- All 24+ service files use `ErrorCode::*` constants
- Consistent error response format across all tools

#### Architecture
- `ToolApiGateway` uses `DrupalToolProvider` internally
- Standardized tool discovery through `ToolProviderInterface`
- Better separation between Drupal-specific and generic MCP code

### Submodules (34 total)

**Core (no contrib dependencies):**
- mcp_tools_analysis, mcp_tools_batch, mcp_tools_blocks, mcp_tools_cache
- mcp_tools_config, mcp_tools_content, mcp_tools_cron, mcp_tools_image_styles
- mcp_tools_layout_builder, mcp_tools_media, mcp_tools_menus, mcp_tools_migration
- mcp_tools_moderation, mcp_tools_structure, mcp_tools_templates, mcp_tools_theme
- mcp_tools_users, mcp_tools_views

**Contrib-dependent:**
- mcp_tools_entity_clone, mcp_tools_jsonapi, mcp_tools_metatag, mcp_tools_paragraphs
- mcp_tools_pathauto, mcp_tools_recipes, mcp_tools_redirect, mcp_tools_scheduler
- mcp_tools_search_api, mcp_tools_sitemap, mcp_tools_ultimate_cron, mcp_tools_webform

**Infrastructure:**
- mcp_tools_stdio (STDIO transport)
- mcp_tools_remote (HTTP transport)
- mcp_tools_mcp_server (MCP Server integration)
- mcp_tools_observability (Telemetry)

### Requirements

- Drupal 10.3+ or 11
- PHP 8.3+
- [Tool API](https://www.drupal.org/project/tool) module

### Upgrade Path

From alpha26:
```bash
composer update drupal/mcp_tools
drush cr
```

No database updates required. Configuration is backward compatible.

### Known Issues

None at this time.

### Contributors

- Code Wheel team
- Claude AI (code review and testing assistance)

### Links

- [GitHub Repository](https://github.com/code-wheel/mcp-tools)
- [Quick Start Guide](https://github.com/code-wheel/mcp-tools/blob/master/docs/QUICKSTART.md)
- [Issue Queue](https://www.drupal.org/project/issues/mcp_tools)
