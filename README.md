# MCP Tools

[![CI](https://github.com/code-wheel/mcp-tools/actions/workflows/ci.yml/badge.svg)](https://github.com/code-wheel/mcp-tools/actions/workflows/ci.yml)
[![Security](https://github.com/code-wheel/mcp-tools/actions/workflows/security.yml/badge.svg)](https://github.com/code-wheel/mcp-tools/actions/workflows/security.yml)

Batteries-included MCP tools for AI assistants working with Drupal sites.

## Version Compatibility

| Drupal Version | PHP Version | Status | Notes |
|----------------|-------------|--------|-------|
| **10.2.x** | 8.2 | ✅ Tested | Minimum supported version |
| **10.3.x** | 8.3 | ✅ Tested | Fully supported |
| **11.0.x** | 8.4, 8.5 | ✅ Tested | Fully supported |

**PHP Support:** 8.2, 8.3, 8.4, 8.5 (PHP 8.1 is EOL as of December 2025)

CI runs tests against all supported Drupal versions on every push.

## Overview

MCP Tools provides curated, high-value tools that solve real problems—not generic CRUD. Inspired by [Sentry MCP](https://docs.sentry.io/product/sentry-mcp/).

**Current:** 205 tools total (23 read-only + 182 write/analysis operations across 29 submodules)

**Full AI-powered site building** - create content types, fields, roles, taxonomies, views, blocks, media, webforms, themes, layouts, and apply recipes through natural conversation.

```
User: "Create a blog with articles, categories, tags, and an editor role"
AI:   Creates content type, fields, vocabularies, role, and permissions
```

**Admin UI** - Configure settings at `/admin/config/services/mcp-tools` including access control, rate limiting, and webhook notifications.

## Recommended Usage

> **MCP Tools is designed primarily for LOCAL DEVELOPMENT and prototyping.**

| Environment | Read Tools | Write Tools | Recommendation |
|-------------|------------|-------------|----------------|
| **Local dev** | ✅ Safe | ✅ Safe | Full functionality |
| **Staging** | ✅ Safe | ⚠️ Caution | Use read-only mode or limited scopes |
| **Production** | ⚠️ Careful | ❌ Not recommended | Read-only mode strongly advised |

**Why write tools are risky in production:**
- Creates configuration in database, not in version-controlled code
- AI assistants can be manipulated via prompt injection
- No human review step before changes are applied
- Can cause configuration drift from your Git repository

**Ideal workflow:**
1. Use MCP Tools locally to scaffold your site
2. Export configuration: `drush config:export`
3. Commit to Git and deploy through normal CI/CD
4. Keep production in read-only mode

## Requirements

- Drupal 10.2+ or Drupal 11
- [MCP Server](https://www.drupal.org/project/mcp_server) module
- [Tool API](https://www.drupal.org/project/tool) module

## Installation

```bash
composer require drupal/mcp_tools
drush en mcp_tools
```

## Architecture: Granular Submodules

MCP Tools uses a **modular architecture** where each functional area is a separate submodule. This allows you to enable only the capabilities you need.

```
mcp_tools/                        # Base module (22 read-only tools)
├── src/
│   ├── Form/SettingsForm.php     # Admin UI at /admin/config/services/mcp-tools
│   └── Service/
│       ├── AccessManager.php     # Three-layer access control
│       ├── RateLimiter.php       # Rate limiting for write operations
│       ├── AuditLogger.php       # Audit logging with sanitization
│       ├── WebhookNotifier.php   # Webhook notifications
│       └── ErrorFormatter.php    # Standardized error responses
└── modules/
    ├── mcp_tools_content/        # Content CRUD (4 tools)
    ├── mcp_tools_structure/      # Content types, fields, taxonomy, roles (12 tools)
    ├── mcp_tools_users/          # User management (5 tools)
    ├── mcp_tools_menus/          # Menu management (5 tools)
    ├── mcp_tools_views/          # Views management (6 tools)
    ├── mcp_tools_blocks/         # Block placement (5 tools)
    ├── mcp_tools_media/          # Media management (6 tools)
    ├── mcp_tools_webform/        # Webform integration (7 tools)
    ├── mcp_tools_theme/          # Theme settings (8 tools)
    ├── mcp_tools_layout_builder/ # Layout Builder (9 tools)
    ├── mcp_tools_recipes/        # Drupal Recipes (6 tools)
    ├── mcp_tools_config/         # Configuration management (5 tools)
    ├── mcp_tools_paragraphs/     # Paragraphs integration (6 tools)
    ├── mcp_tools_moderation/     # Content Moderation (6 tools)
    ├── mcp_tools_scheduler/      # Scheduled publish (5 tools)
    ├── mcp_tools_metatag/        # SEO meta tags (5 tools)
    ├── mcp_tools_image_styles/   # Image styles (7 tools)
    ├── mcp_tools_cache/          # Cache management (6 tools)
    ├── mcp_tools_cron/           # Cron management (5 tools)
    ├── mcp_tools_ultimate_cron/  # Ultimate Cron (6 tools)
    ├── mcp_tools_pathauto/       # URL aliases (6 tools)
    ├── mcp_tools_redirect/       # URL redirects (7 tools)
    ├── mcp_tools_sitemap/        # XML sitemap (7 tools)
    ├── mcp_tools_search_api/     # Search API (8 tools)
    ├── mcp_tools_entity_clone/   # Entity cloning (4 tools)
    ├── mcp_tools_analysis/       # Site analysis (8 tools)
    ├── mcp_tools_batch/          # Bulk operations (6 tools)
    ├── mcp_tools_templates/      # Site templates (5 tools)
    └── mcp_tools_migration/      # Content migration (7 tools)
```

Enable submodules as needed:

```bash
# Enable specific capabilities
drush en mcp_tools_content        # Content CRUD
drush en mcp_tools_structure      # Site building (content types, fields, roles)
drush en mcp_tools_users          # User management
drush en mcp_tools_menus          # Menu management
drush en mcp_tools_views          # Views creation
drush en mcp_tools_blocks         # Block placement
drush en mcp_tools_media          # Media management
drush en mcp_tools_webform        # Webform integration
drush en mcp_tools_theme          # Theme settings
drush en mcp_tools_layout_builder # Layout Builder
drush en mcp_tools_recipes        # Drupal Recipes (10.3+)
drush en mcp_tools_config         # Configuration management
drush en mcp_tools_paragraphs     # Paragraphs integration
drush en mcp_tools_moderation     # Content Moderation workflows
drush en mcp_tools_scheduler      # Scheduled publish/unpublish
drush en mcp_tools_metatag        # SEO meta tags
drush en mcp_tools_image_styles   # Image styles and effects
drush en mcp_tools_cache          # Cache management
drush en mcp_tools_cron           # Cron and queue management
drush en mcp_tools_ultimate_cron  # Ultimate Cron job management
drush en mcp_tools_pathauto       # URL alias patterns
drush en mcp_tools_redirect       # URL redirects
drush en mcp_tools_sitemap        # XML sitemap management
drush en mcp_tools_search_api     # Search API indexes
drush en mcp_tools_entity_clone   # Entity cloning
drush en mcp_tools_analysis       # Site analysis tools
drush en mcp_tools_batch          # Bulk operations
drush en mcp_tools_templates      # Site templates
drush en mcp_tools_migration      # Content import/export
```

## Access Control

MCP Tools provides three layers of access control:

### 1. Module-Based Access
Only enabled submodules expose their tools.

### 2. Global Read-Only Mode
Site-wide toggle to disable all write operations:

```php
// In settings.php or via config
$config['mcp_tools.settings']['access']['read_only_mode'] = TRUE;
```

### 3. Connection Scopes
Per-connection access levels via header, query param, or environment variable:

```bash
# Via HTTP header
X-MCP-Scope: read,write

# Via query parameter
?mcp_scope=read,write

# Via environment (for STDIO transport)
MCP_SCOPE=read,write drush mcp:serve
```

Available scopes:
- `read` - Read-only operations
- `write` - Write operations
- `admin` - Administrative operations

## Read-Only Tools (22)

### Site Health

| Tool | Description |
|------|-------------|
| `get_site_status` | Drupal/PHP version, module counts, cron, maintenance mode |
| `get_system_status` | System requirements, PHP info, database status |
| `check_security_updates` | Security updates for core and contrib |
| `check_cron_status` | Cron health and last run time |
| `analyze_watchdog` | Log analysis - errors, warnings, summaries |
| `get_queue_status` | Queue item counts and worker status |
| `get_file_system_status` | Directory permissions, disk usage |

### Content

| Tool | Description |
|------|-------------|
| `list_content_types` | Content types with field definitions |
| `get_recent_content` | Recently created/modified content |
| `search_content` | Title-based content search |
| `get_vocabularies` | Taxonomy vocabularies with term counts |
| `get_terms` | Terms from vocabulary (flat or hierarchical) |
| `get_files` | Managed files with MIME breakdown |
| `find_orphaned_files` | Unused file detection |

### Configuration

| Tool | Description |
|------|-------------|
| `get_config_status` | Config sync status (active vs staged) |
| `get_config` | View specific configuration object |
| `list_config` | List config names with optional prefix filter |

### Users

| Tool | Description |
|------|-------------|
| `get_roles` | Roles with permissions |
| `get_users` | User accounts, status, activity |
| `get_permissions` | All permissions by provider |

### Structure

| Tool | Description |
|------|-------------|
| `get_menus` | All menus with link counts |
| `get_menu_tree` | Hierarchical menu structure |

### Discovery

| Tool | Description |
|------|-------------|
| `mcp_tools_list_available` | List all available MCP tools by category or search |

## Write Submodules

### mcp_tools_content (4 tools)

| Tool | Description |
|------|-------------|
| `mcp_create_content` | Create nodes with field values |
| `mcp_update_content` | Update existing content (creates revision) |
| `mcp_delete_content` | Permanently delete content |
| `mcp_publish_content` | Publish or unpublish content |

### mcp_tools_structure (12 tools)

| Tool | Description |
|------|-------------|
| `mcp_structure_create_content_type` | Create new content types with body field |
| `mcp_structure_delete_content_type` | Remove custom content types |
| `mcp_structure_add_field` | Add fields to content types (18 field types) |
| `mcp_structure_delete_field` | Remove fields from content types |
| `mcp_structure_list_field_types` | List available field types |
| `mcp_structure_create_vocabulary` | Create taxonomy vocabularies |
| `mcp_structure_create_term` | Create individual taxonomy terms |
| `mcp_structure_create_terms` | Bulk create taxonomy terms |
| `mcp_structure_create_role` | Create user roles |
| `mcp_structure_delete_role` | Remove custom roles |
| `mcp_structure_grant_permissions` | Grant permissions to roles |
| `mcp_structure_revoke_permissions` | Revoke permissions from roles |

**Safety:** Dangerous permissions blocked (administer permissions, administer users, etc.)

### mcp_tools_users (5 tools)

| Tool | Description |
|------|-------------|
| `mcp_users_create_user` | Create user accounts with roles |
| `mcp_users_update_user` | Update email, status, roles |
| `mcp_users_block_user` | Block a user account |
| `mcp_users_activate_user` | Activate a blocked user |
| `mcp_users_assign_roles` | Assign roles to users |

**Safety:** Cannot modify uid 1 (super admin) or assign administrator role.

### mcp_tools_menus (5 tools)

| Tool | Description |
|------|-------------|
| `mcp_menus_create_menu` | Create new menus |
| `mcp_menus_delete_menu` | Remove custom menus |
| `mcp_menus_add_link` | Add links to menus |
| `mcp_menus_update_link` | Update menu link properties |
| `mcp_menus_delete_link` | Remove menu links |

**Safety:** System menus (admin, main, footer, etc.) protected from deletion.

### mcp_tools_views (6 tools)

| Tool | Description |
|------|-------------|
| `mcp_views_create_view` | Create custom views |
| `mcp_views_create_content_list` | Quick content listing view |
| `mcp_views_delete_view` | Remove custom views |
| `mcp_views_add_display` | Add display to existing view |
| `mcp_views_enable` | Enable a view |
| `mcp_views_disable` | Disable a view |

**Safety:** Core views protected from deletion.

### mcp_tools_blocks (5 tools)

| Tool | Description |
|------|-------------|
| `mcp_blocks_place` | Place a block in a region |
| `mcp_blocks_remove` | Remove a placed block |
| `mcp_blocks_configure` | Configure block settings |
| `mcp_blocks_list_available` | List available blocks |
| `mcp_blocks_list_regions` | List theme regions |

### mcp_tools_media (6 tools)

| Tool | Description |
|------|-------------|
| `mcp_media_create_type` | Create media types |
| `mcp_media_delete_type` | Remove media types |
| `mcp_media_upload_file` | Upload files (base64 support) |
| `mcp_media_create` | Create media entities |
| `mcp_media_delete` | Delete media entities |
| `mcp_media_list_types` | List available media types |

### mcp_tools_webform (7 tools)

| Tool | Description |
|------|-------------|
| `mcp_webform_list` | List all webforms |
| `mcp_webform_get` | Get webform details |
| `mcp_webform_get_submissions` | Get form submissions |
| `mcp_webform_create` | Create new webforms |
| `mcp_webform_update` | Update webform settings |
| `mcp_webform_delete` | Delete webforms |
| `mcp_webform_delete_submission` | Delete individual submissions |

### mcp_tools_theme (8 tools)

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

**Safety:** Cannot disable the active default theme or admin theme.

### mcp_tools_layout_builder (9 tools)

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

**Requires:** `drupal:layout_builder` module.

### mcp_tools_recipes (6 tools)

| Tool | Description |
|------|-------------|
| `mcp_recipes_list` | List available recipes |
| `mcp_recipes_get` | Get recipe details |
| `mcp_recipes_validate` | Validate recipe before applying |
| `mcp_recipes_apply` | Apply a recipe to the site |
| `mcp_recipes_applied` | List applied recipes |
| `mcp_recipes_create` | Create a new recipe |

**Requires:** Drupal 10.3+ for full recipe support. Apply operations require `admin` scope.

### mcp_tools_config (5 tools)

| Tool | Description |
|------|-------------|
| `mcp_config_changes` | List config that differs from sync directory |
| `mcp_config_export` | Export configuration to sync directory |
| `mcp_config_mcp_changes` | List config created/modified via MCP |
| `mcp_config_diff` | Show diff between active and sync config |
| `mcp_config_preview` | Dry-run mode: preview what an operation would do |

**Key for config management:** Use `mcp_config_mcp_changes` to see what MCP created, then `mcp_config_export` to save it.

### mcp_tools_paragraphs (6 tools)

| Tool | Description |
|------|-------------|
| `mcp_paragraphs_list_types` | List all paragraph types with fields |
| `mcp_paragraphs_get_type` | Get details of a paragraph type |
| `mcp_paragraphs_create_type` | Create new paragraph types |
| `mcp_paragraphs_delete_type` | Delete paragraph types |
| `mcp_paragraphs_add_field` | Add fields to paragraph types |
| `mcp_paragraphs_delete_field` | Remove fields from paragraph types |

**Requires:** `paragraphs:paragraphs` module.

### mcp_tools_moderation (6 tools)

| Tool | Description |
|------|-------------|
| `mcp_moderation_get_workflows` | List all content moderation workflows |
| `mcp_moderation_get_workflow` | Get details of a specific workflow |
| `mcp_moderation_get_state` | Get moderation state of an entity |
| `mcp_moderation_set_state` | Set moderation state (draft, review, published) |
| `mcp_moderation_get_history` | Get moderation state history |
| `mcp_moderation_get_by_state` | Find content in a specific state |

**Requires:** `drupal:content_moderation` and `drupal:workflows` modules.

### mcp_tools_scheduler (5 tools)

| Tool | Description |
|------|-------------|
| `mcp_scheduler_get_scheduled` | List all scheduled content |
| `mcp_scheduler_publish` | Schedule content for future publishing |
| `mcp_scheduler_unpublish` | Schedule content for future unpublishing |
| `mcp_scheduler_cancel` | Cancel scheduled publishing/unpublishing |
| `mcp_scheduler_get_schedule` | Get schedule for a specific entity |

**Requires:** `scheduler:scheduler` contrib module.

### mcp_tools_metatag (5 tools)

| Tool | Description |
|------|-------------|
| `mcp_metatag_get_defaults` | Get default metatags by entity type |
| `mcp_metatag_get_entity` | Get metatags for a specific entity |
| `mcp_metatag_set_entity` | Set metatags for an entity |
| `mcp_metatag_list_groups` | List available metatag groups |
| `mcp_metatag_list_tags` | List available metatag definitions |

**Requires:** `metatag:metatag` contrib module.

### mcp_tools_image_styles (7 tools)

| Tool | Description |
|------|-------------|
| `mcp_image_styles_list` | List all image styles with effects |
| `mcp_image_styles_get` | Get details of a specific image style |
| `mcp_image_styles_create` | Create a new image style |
| `mcp_image_styles_delete` | Delete an image style |
| `mcp_image_styles_add_effect` | Add effect to style (scale, crop, etc.) |
| `mcp_image_styles_remove_effect` | Remove effect from style |
| `mcp_image_styles_list_effects` | List available image effect plugins |

**Requires:** `drupal:image` core module.

### mcp_tools_cache (6 tools)

| Tool | Description |
|------|-------------|
| `mcp_cache_get_status` | Get cache status (bins, backends, sizes) |
| `mcp_cache_clear_all` | Clear all caches (drush cr) |
| `mcp_cache_clear_bin` | Clear a specific cache bin |
| `mcp_cache_invalidate_tags` | Invalidate specific cache tags |
| `mcp_cache_clear_entity` | Clear cache for a specific entity |
| `mcp_cache_rebuild` | Rebuild router, theme, container, or menu |

### mcp_tools_cron (5 tools)

| Tool | Description |
|------|-------------|
| `mcp_cron_get_status` | Get cron status and registered jobs |
| `mcp_cron_run` | Execute all cron jobs immediately |
| `mcp_cron_run_queue` | Process items from a specific queue |
| `mcp_cron_update_settings` | Update cron autorun threshold |
| `mcp_cron_reset_key` | Generate a new cron key |

### mcp_tools_ultimate_cron (6 tools)

| Tool | Description |
|------|-------------|
| `mcp_ultimate_cron_list_jobs` | List all Ultimate Cron jobs with status |
| `mcp_ultimate_cron_get_job` | Get job details |
| `mcp_ultimate_cron_run` | Run a specific job immediately |
| `mcp_ultimate_cron_enable` | Enable a disabled job |
| `mcp_ultimate_cron_disable` | Disable a job |
| `mcp_ultimate_cron_logs` | Get recent logs for a job |

**Requires:** `ultimate_cron:ultimate_cron` contrib module.

### mcp_tools_pathauto (6 tools)

| Tool | Description |
|------|-------------|
| `mcp_pathauto_list_patterns` | List all URL alias patterns |
| `mcp_pathauto_get_pattern` | Get pattern details |
| `mcp_pathauto_create` | Create a new alias pattern |
| `mcp_pathauto_update` | Update an existing pattern |
| `mcp_pathauto_delete` | Delete a pattern |
| `mcp_pathauto_generate` | Bulk generate aliases for entities |

**Requires:** `pathauto:pathauto` contrib module.

### mcp_tools_redirect (7 tools)

| Tool | Description |
|------|-------------|
| `mcp_redirect_list` | List all redirects with pagination |
| `mcp_redirect_get` | Get redirect details |
| `mcp_redirect_create` | Create a new redirect |
| `mcp_redirect_update` | Update an existing redirect |
| `mcp_redirect_delete` | Delete a redirect |
| `mcp_redirect_find` | Find redirect by source path |
| `mcp_redirect_import` | Bulk import redirects |

**Requires:** `redirect:redirect` contrib module.

### mcp_tools_sitemap (7 tools)

| Tool | Description |
|------|-------------|
| `mcp_sitemap_status` | Get sitemap generation status |
| `mcp_sitemap_list` | List all sitemap variants |
| `mcp_sitemap_get_settings` | Get sitemap settings |
| `mcp_sitemap_update_settings` | Update sitemap settings |
| `mcp_sitemap_regenerate` | Regenerate sitemap |
| `mcp_sitemap_entity_settings` | Get entity inclusion settings |
| `mcp_sitemap_set_entity` | Set entity inclusion in sitemap |

**Requires:** `simple_sitemap:simple_sitemap` contrib module.

### mcp_tools_search_api (8 tools)

| Tool | Description |
|------|-------------|
| `mcp_search_api_list_indexes` | List all search indexes |
| `mcp_search_api_get_index` | Get index details (fields, datasources) |
| `mcp_search_api_status` | Get indexing status |
| `mcp_search_api_reindex` | Mark items for reindexing |
| `mcp_search_api_index` | Index a batch of items |
| `mcp_search_api_clear` | Clear all indexed data |
| `mcp_search_api_list_servers` | List search servers |
| `mcp_search_api_get_server` | Get server details |

**Requires:** `search_api:search_api` contrib module.

### mcp_tools_entity_clone (4 tools)

| Tool | Description |
|------|-------------|
| `mcp_entity_clone_clone` | Clone a single entity |
| `mcp_entity_clone_with_refs` | Clone entity with referenced entities |
| `mcp_entity_clone_types` | List cloneable entity types |
| `mcp_entity_clone_settings` | Get clone settings for a bundle |

**Requires:** `entity_clone:entity_clone` contrib module.

### mcp_tools_analysis (8 tools)

| Tool | Description |
|------|-------------|
| `mcp_analysis_broken_links` | Scan content for broken internal links |
| `mcp_analysis_content_audit` | Find stale/orphaned content and drafts |
| `mcp_analysis_seo` | Analyze SEO (meta tags, headings, alt text) |
| `mcp_analysis_security` | Security audit (permissions, exposed data) |
| `mcp_analysis_unused_fields` | Find fields with no data |
| `mcp_analysis_performance` | Analyze cache and performance |
| `mcp_analysis_accessibility` | Basic accessibility checks (WCAG) |
| `mcp_analysis_duplicates` | Find duplicate/similar content |

**No dependencies** - works with core only.

### mcp_tools_batch (6 tools)

| Tool | Description |
|------|-------------|
| `mcp_batch_create_content` | Create multiple content items (max 50) |
| `mcp_batch_update_content` | Update multiple content items |
| `mcp_batch_delete_content` | Delete multiple content items |
| `mcp_batch_publish` | Publish/unpublish multiple items |
| `mcp_batch_assign_roles` | Assign roles to multiple users |
| `mcp_batch_create_terms` | Create multiple taxonomy terms |

**Limits:** 50 items per batch operation to prevent timeouts.

### mcp_tools_templates (5 tools)

| Tool | Description |
|------|-------------|
| `mcp_templates_list` | List available site templates |
| `mcp_templates_get` | Get template details |
| `mcp_templates_apply` | Apply a template to the site |
| `mcp_templates_preview` | Preview what a template will create |
| `mcp_templates_export` | Export current site config as template |

**Built-in templates:** blog, portfolio, business, documentation.

### mcp_tools_migration (7 tools)

| Tool | Description |
|------|-------------|
| `mcp_migration_import_csv` | Import content from CSV |
| `mcp_migration_import_json` | Import content from JSON |
| `mcp_migration_validate` | Validate import data before importing |
| `mcp_migration_field_mapping` | Get field mapping for a content type |
| `mcp_migration_export_csv` | Export content to CSV |
| `mcp_migration_export_json` | Export content to JSON |
| `mcp_migration_status` | Get import/export job status |

**Limits:** 100 items per import/export operation.

## Example Prompts

**Site Health:**
- "What's the status of my Drupal site?"
- "Are there any security updates?"
- "Show me recent errors from the log"

**Site Building:**
- "Create an Article content type with body, image, and tags fields"
- "Add a Tags vocabulary with some default terms"
- "Create an Editor role with content editing permissions"
- "Create a view showing recent articles"
- "Place a block in the sidebar showing recent content"

**Content:**
- "Create a new blog post titled 'Hello World'"
- "Add an About link to the main menu"
- "Upload this image and create a media entity"

**Forms:**
- "Create a contact form with name, email, and message fields"
- "Show me submissions from the contact form"

**Themes:**
- "What theme is currently active?"
- "Switch to the Olivero theme"
- "Update the site logo"

**Layout Builder:**
- "Enable Layout Builder for the Article content type"
- "Add a two-column section to the Article layout"
- "Place a block in the sidebar region"

**Recipes:**
- "What recipes are available?"
- "Apply the 'blog' recipe to set up a blog"

## Security

### Built-in Protections

- **Modular by default** - Enable only the submodules you need
- **Three-layer access control** - Modules, global toggle, connection scopes
- **Permission-based** - Each category has its own Drupal permission
- **Audit logging** - All write operations logged with user info
- **Sensitive data redaction** - Passwords and secrets never logged
- **Protected entities** - uid 1, administrator role, core views/menus protected
- **Dangerous permissions blocked** - Cannot grant site admin permissions via MCP

### Protected Entities

| Entity | Protection |
|--------|------------|
| User ID 1 | Cannot be modified or blocked |
| Administrator role | Cannot be assigned via MCP |
| System menus | admin, main, footer, tools, account protected |
| Core views | Cannot delete core-provided views |
| Active themes | Cannot disable current default/admin theme |

### Blocked Permissions

These permissions can never be granted via MCP:
- `administer permissions`
- `administer users`
- `administer site configuration`
- `administer modules`
- `administer software updates`
- `administer themes`
- `bypass node access`
- `synchronize configuration`
- `import configuration`
- `export configuration`

### Security Considerations

| Risk | Mitigation | Status |
|------|------------|--------|
| **Prompt injection** | Malicious content could instruct AI | ⚠️ Use read-only in production |
| **Privilege escalation** | Blocked dangerous permissions | ✅ Implemented |
| **Content injection (XSS)** | Relies on Drupal's text filtering | ✅ Drupal handles |
| **DoS via mass creation** | Rate limiting | ✅ Implemented |
| **Data exfiltration** | Read tools expose site info | ⚠️ Use proper auth |
| **Config drift** | Changes not in Git | ⚠️ Export after changes |

### Production Hardening

If you must use MCP Tools in production:

```php
// settings.php - Enable read-only mode
$config['mcp_tools.settings']['access']['read_only_mode'] = TRUE;

// Or allow only read scope by default
$config['mcp_tools.settings']['access']['default_scopes'] = ['read'];

// Enable rate limiting
$config['mcp_tools.settings']['rate_limiting']['enabled'] = TRUE;
$config['mcp_tools.settings']['rate_limiting']['max_writes_per_minute'] = 10;
$config['mcp_tools.settings']['rate_limiting']['max_writes_per_hour'] = 100;
```

**Additional recommendations:**
1. Use IP allowlisting at the web server level
2. Require authentication for MCP endpoints
3. Monitor audit logs for unusual activity
4. Keep write submodules disabled in production
5. Use separate environments for AI-assisted development

### Configuration Management Warning

MCP Tools creates configuration directly in the database. This can cause **config drift**:

```
Developer commits config to Git
  ↓
Deploys to production
  ↓
AI creates new content type via MCP (in database only!)
  ↓
Next deploy: Config conflict or overwrite!
```

**Best practice:** Always export config after using write tools:
```bash
# After using MCP to create structures
drush config:export
git add config/
git commit -m "Export MCP-created configuration"
```

## Testing

PHPUnit tests are included for all services:

```bash
cd mcp_tools
../vendor/bin/phpunit
```

## Services

All submodules share core services from the base module:

| Service | Description |
|---------|-------------|
| `mcp_tools.access_manager` | Access control with scopes |
| `mcp_tools.audit_logger` | Audit logging with sanitization |
| `mcp_tools.rate_limiter` | Rate limiting for write operations |
| `mcp_tools.webhook_notifier` | Webhook notifications for external systems |

### Webhook Notifications

MCP Tools can send notifications to external systems (Slack, audit logs, etc.) when operations occur:

```php
// settings.php - Enable webhooks
$config['mcp_tools.settings']['webhooks']['enabled'] = TRUE;
$config['mcp_tools.settings']['webhooks']['url'] = 'https://hooks.slack.com/...';
$config['mcp_tools.settings']['webhooks']['secret'] = 'your-hmac-secret';
```

Webhook payloads include:
- Timestamp and operation type (create, update, delete, structure)
- Entity type and ID
- User who performed the action
- Sanitized details (sensitive data automatically redacted)

Signature verification: When a secret is configured, requests include an `X-MCP-Signature` header with an HMAC-SHA256 signature.

## Usage

With MCP Server configured:

```bash
# STDIO transport (Claude Desktop, Claude Code)
drush mcp:serve

# With specific scopes
MCP_SCOPE=read,write drush mcp:serve

# HTTP transport
# Endpoint available at /_mcp after configuration
```

## Contributing

Issues and merge requests: https://www.drupal.org/project/issues/mcp_tools

## License

GPL-2.0-or-later
