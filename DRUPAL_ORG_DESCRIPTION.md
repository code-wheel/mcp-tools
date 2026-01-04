# Drupal.org Project Description

**Summary (200 chars):**
MCP Tools provides 205 AI-powered tools for building Drupal sites through natural conversation. Create content types, fields, views, blocks, users, and more via Claude, ChatGPT, or any MCP client.

---

## Full Description

MCP Tools enables AI assistants to build and manage Drupal sites through the Model Context Protocol (MCP). Instead of manually clicking through admin interfaces, describe what you want in plain English and let AI handle the implementation.

**Example:**
```
You: "Create a blog with articles, categories, tags, and an editor role"
AI:  Creates content type, taxonomy vocabularies, fields, role, and permissions
```

### Features

**205 tools across 29 submodules** covering every aspect of Drupal site building:

**Site Building (Core)**
- Create and manage content types with 18+ field types
- Build taxonomy vocabularies and terms
- Create user roles with granular permissions
- Manage menus and navigation

**Views & Display**
- Create Views with page and block displays
- Place and configure blocks in theme regions
- Manage Layout Builder sections and components
- Configure image styles and effects

**Content Management**
- Create, update, publish, and delete content
- Upload and manage media files
- Build webforms with validation
- Handle content moderation workflows

**Site Administration**
- Monitor site health and security updates
- Manage cron jobs and queues
- Clear and rebuild caches
- Run content analysis and audits

**Advanced Features**
- Apply Drupal Recipes (10.3+)
- Bulk operations (up to 50 items)
- Content migration (CSV/JSON import/export)
- SEO analysis and meta tag management

**Security Built-In**
- Three-layer access control (modules, global toggle, connection scopes)
- Config-only mode (restrict writes to configuration changes)
- Rate limiting for write operations
- Audit logging with sensitive data redaction
- Protected entities (uid 1, admin role, core views)
- Dangerous permissions blocked by default

### Post-Installation

1. **Enable the base module:**
   ```
   drush en mcp_tools
   ```

2. **Enable submodules for the capabilities you need:**
   ```
   drush en mcp_tools_content mcp_tools_structure mcp_tools_views
   ```

3. **Configure settings** at `/admin/config/services/mcp-tools`:
   - Set default connection scopes (read/write/admin)
   - Enable config-only mode for config-as-code workflows
   - Enable rate limiting for production safety
   - Configure audit logging
   - Set up webhook notifications (optional)

4. **Export config (recommended after write tools):**
   ```
   drush cex -y
   ```
   Optionally enable `mcp_tools_config` to preview/export via tools (e.g. `mcp_config_changes`, `mcp_config_preview`, `mcp_config_export`).

5. **Connect your MCP client** (Claude Desktop, Claude Code, or any MCP-compatible assistant)

6. **Start building!** Ask your AI assistant to create content types, add fields, build views, etc.

### Additional Requirements

**Required:**
- Drupal 10.3+ or Drupal 11
- [Tool API](https://www.drupal.org/project/tool) module

**Optional (to expose tools over MCP):**
- **Recommended (local dev):** `mcp_tools_stdio` submodule (STDIO via Drush)
- **Experimental (remote HTTP):** `mcp_tools_remote` submodule (API key auth)
- **Optional bridge:** `mcp_tools_mcp_server` submodule (generates MCP Server tool configs for MCP Tools)
- **Alternative:** [MCP Server](https://www.drupal.org/project/mcp_server) module (note: upstream Composer metadata issue: https://www.drupal.org/project/mcp_server/issues/3560993)

**PHP:**
- PHP 8.3+ (Drupal 11 requires PHP 8.4+)

### Recommended Modules/Libraries

**For full functionality, these contrib modules unlock additional tools:**

| Module | Unlocks |
|--------|---------|
| webform | Form building tools (7 tools) |
| paragraphs | Paragraph type management (6 tools) |
| metatag | SEO meta tag tools (5 tools) |
| pathauto | URL alias pattern tools (6 tools) |
| redirect | Redirect management tools (7 tools) |
| simple_sitemap | XML sitemap tools (7 tools) |
| search_api | Search index tools (8 tools) |
| scheduler | Scheduled publishing tools (5 tools) |
| ultimate_cron | Advanced cron tools (6 tools) |
| entity_clone | Entity cloning tools (4 tools) |

### Similar Projects

**MCP Server** - Another Drupal MCP transport implementation. MCP Tools can be used with MCP Server, or via the built-in `mcp_tools_stdio` / `mcp_tools_remote` transports.

**ECA (Event-Condition-Action)** - Automates Drupal tasks via rules. MCP Tools differs by enabling real-time AI interaction rather than predefined automation rules.

**Admin Toolbar** - Improves admin UX. MCP Tools complements this by enabling voice/text-based administration.

### Supporting This Module

This module is developed and maintained by [Code Wheel](https://github.com/code-wheel).

- Report issues: https://www.drupal.org/project/issues/mcp_tools
- GitHub: https://github.com/code-wheel/mcp-tools

### Community Documentation

**Getting Started:**
- [MCP Protocol Overview](https://modelcontextprotocol.io/)
- [Claude Desktop MCP Setup](https://claude.ai/docs/mcp)

**Version Compatibility:**

| Drupal | PHP | Status |
|--------|-----|--------|
| 10.3.x | 8.3 | Tested |
| 11.x | 8.4 | Tested |

---

## Submodule Reference

| Submodule | Tools | Description |
|-----------|-------|-------------|
| mcp_tools (base) | 23 | Read-only site status, health, content listing |
| mcp_tools_mcp_server | 0 | Optional bridge for drupal/mcp_server |
| mcp_tools_content | 4 | Content CRUD operations |
| mcp_tools_structure | 12 | Content types, fields, taxonomy, roles |
| mcp_tools_users | 5 | User account management |
| mcp_tools_menus | 5 | Menu and link management |
| mcp_tools_views | 6 | Views creation and management |
| mcp_tools_blocks | 5 | Block placement and configuration |
| mcp_tools_media | 6 | Media type and file management |
| mcp_tools_webform | 7 | Webform building |
| mcp_tools_theme | 8 | Theme switching and settings |
| mcp_tools_layout_builder | 9 | Layout Builder management |
| mcp_tools_recipes | 6 | Drupal Recipe application |
| mcp_tools_config | 5 | Configuration management |
| mcp_tools_paragraphs | 6 | Paragraph type management |
| mcp_tools_moderation | 6 | Content moderation workflows |
| mcp_tools_scheduler | 5 | Scheduled publishing |
| mcp_tools_metatag | 5 | SEO meta tags |
| mcp_tools_image_styles | 7 | Image style effects |
| mcp_tools_cache | 6 | Cache management |
| mcp_tools_cron | 5 | Cron and queue management |
| mcp_tools_ultimate_cron | 6 | Ultimate Cron integration |
| mcp_tools_pathauto | 6 | URL alias patterns |
| mcp_tools_redirect | 7 | URL redirects |
| mcp_tools_sitemap | 7 | XML sitemap |
| mcp_tools_search_api | 8 | Search API indexes |
| mcp_tools_entity_clone | 4 | Entity cloning |
| mcp_tools_analysis | 8 | Site analysis and audits |
| mcp_tools_batch | 6 | Bulk operations |
| mcp_tools_templates | 5 | Site templates |
| mcp_tools_migration | 7 | Content import/export |

**Total: 205 tools**
