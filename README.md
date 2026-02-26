# MCP Tools

[![CI](https://github.com/code-wheel/mcp-tools/actions/workflows/ci.yml/badge.svg)](https://github.com/code-wheel/mcp-tools/actions/workflows/ci.yml)
[![Security](https://github.com/code-wheel/mcp-tools/actions/workflows/security.yml/badge.svg)](https://github.com/code-wheel/mcp-tools/actions/workflows/security.yml)
[![codecov](https://codecov.io/gh/code-wheel/mcp-tools/branch/master/graph/badge.svg)](https://codecov.io/gh/code-wheel/mcp-tools)

Batteries-included MCP tools for AI assistants working with Drupal sites.

## Version Compatibility

| Drupal Version | PHP Version | Status | Notes |
|----------------|-------------|--------|-------|
| **10.3.x** | 8.3 | ✅ Tested | Minimum supported version |
| **11.x** | 8.4 | ✅ Tested | Drupal 11 requires PHP 8.4+ |

**PHP Support:** 8.3, 8.4

CI runs tests against all supported Drupal versions on every push.

## Overview

MCP Tools provides curated, high-value tools that solve real problems—not generic CRUD. Inspired by [Sentry MCP](https://docs.sentry.io/product/sentry-mcp/).

**Current:** 222 tools total (25 read-only + 197 write/analysis operations across 34 submodules)

**Resources:** MCP Tools now exposes read-only resources (e.g., `drupal://site/status`, `drupal://site/snapshot`) for lightweight site context, including blueprint + config drift summary.
**Prompts:** MCP Tools now exposes prompts (e.g., `mcp_tools/site-brief`) for reusable analysis instructions.
**Observability hooks:** MCP Tools dispatches tool execution events for custom logging, metrics, or webhooks.

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
| **Staging** | ✅ Safe | ⚠️ Caution | Use config-only mode or limited scopes |
| **Production** | ⚠️ Careful | ❌ Not recommended | Read-only mode strongly advised (config-only if unavoidable) |

**Why write tools are risky in production:**
- Creates configuration in database, not in version-controlled code
- AI assistants can be manipulated via prompt injection
- No human review step before changes are applied
- Can cause configuration drift from your Git repository

**Ideal workflow:**
1. Use MCP Tools locally to scaffold your site
2. Enable config-only mode to keep changes reviewable as code
3. Export configuration: `drush config:export`
4. Commit to Git and deploy through normal CI/CD
5. Keep production in read-only mode

## Requirements

- Drupal 10.3+ or Drupal 11
- [Tool API](https://www.drupal.org/project/tool) module

### MCP Transports (choose one)

- **Recommended (local dev):** `mcp_tools_stdio` — runs an MCP server over STDIO via Drush.
- **Experimental (remote HTTP):** `mcp_tools_remote` — exposes an HTTP endpoint with API key authentication.
- **Optional (MCP Server bridge):** `mcp_tools_mcp_server` — generates MCP Server tool configs for MCP Tools (only relevant if you install `drupal/mcp_server`).
- **Alternative:** [MCP Server](https://www.drupal.org/project/mcp_server) (optional). Note: `drupal/mcp_server` currently has an upstream Composer metadata issue; see https://www.drupal.org/project/mcp_server/issues/3560993 for the workaround.

## Installation

```bash
composer require drupal/mcp_tools
drush en mcp_tools
```

### Local MCP (STDIO) setup (recommended)

```bash
drush en mcp_tools_stdio
drush mcp-tools:serve --uid=1
```

Tip: Drush often boots as uid 0 (anonymous). For local development, use `--uid=1`. For shared environments, use a dedicated user with only the MCP Tools permissions you need.

**Gateway mode (optional):** expose only the discover/info/execute tools to reduce tool list size.

```bash
drush mcp-tools:serve --uid=1 --gateway
```

Gateway tools:
- `mcp_tools/discover-tools`
- `mcp_tools/get-tool-info`
- `mcp_tools/execute-tool`

### Remote MCP (HTTP) setup (experimental)

```bash
drush en mcp_tools_remote
drush mcp-tools:remote-key-create --label="My Key" --scopes=read --ttl=86400
```

Configure the endpoint at `/_mcp_tools` in your MCP client, and send the key as `Authorization: Bearer …` or `X-MCP-Api-Key: …`.

Only use this on trusted internal networks. Configure the execution user at `/admin/config/services/mcp-tools/remote` (use "uid 1" checkbox for development, or create a dedicated mcp_executor account for production). Consider setting IP and Origin/Host allowlists, and keep keys read-only unless absolutely necessary.

To reduce tool list size for remote clients, enable **Gateway mode** in the remote settings UI. This exposes only the discover/info/execute tools while still allowing execution of any allowed tool by name.

## Observability hooks

MCP Tools dispatches PSR-14 events during tool execution. Subscribe to these classes from `code-wheel/mcp-events`:
- `CodeWheel\McpEvents\ToolExecutionStartedEvent`
- `CodeWheel\McpEvents\ToolExecutionSucceededEvent`
- `CodeWheel\McpEvents\ToolExecutionFailedEvent`

Events include tool name, plugin ID, sanitized arguments, request ID, and execution duration. Failed events include a reason constant (e.g., `REASON_VALIDATION`, `REASON_ACCESS_DENIED`).

Enable the optional `mcp_tools_observability` submodule to log execution events to watchdog.

## Docs

- `mcp_tools/docs/QUICKSTART.md` — 5-minute onboarding
- `mcp_tools/docs/TROUBLESHOOTING.md` — Common errors and fixes
- `mcp_tools/docs/CLIENT_INTEGRATIONS.md` — MCP client configs (STDIO + HTTP)
- `mcp_tools/docs/USE_CASES.md` — Real-world workflows

## Architecture: Granular Submodules

MCP Tools uses a **modular architecture** where each functional area is a separate submodule. This allows you to enable only the capabilities you need.

```
mcp_tools/                        # Base module (25 read-only tools)
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
    ├── mcp_tools_observability/  # Tool execution logging subscriber
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

Common starter bundles:

```bash
# Core site builder (local dev)
drush en mcp_tools_structure mcp_tools_views mcp_tools_blocks mcp_tools_menus mcp_tools_users mcp_tools_content mcp_tools_media -y

# Ops (use with care)
drush en mcp_tools_cache mcp_tools_cron mcp_tools_batch mcp_tools_analysis -y
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
Per-connection access levels (read/write/admin).

**Default:** new installs start with `read` only via `access.default_scopes`.

**Security default:** HTTP scope overrides are disabled by default. Enable them only if you have a trusted reverse proxy stripping/overwriting client-supplied scope headers/params.

```bash
# Via HTTP header
X-MCP-Scope: read,write

# Via query parameter
?mcp_scope=read,write

# Via environment (for STDIO transport)
MCP_SCOPE=read,write drush mcp-tools:serve --uid=1
```

## Server Profiles (YAML-only)

Define multiple MCP server profiles in `mcp_tools_servers.settings.yml` and select them via the STDIO `--server` option or the remote `server_id` setting.

New installs include `development`, `staging`, and `production` presets; update `default_server` to point at the one you want.

```yaml
default_server: default
servers:
  default:
    name: 'Drupal MCP Tools'
    version: '1.0.0'
    pagination_limit: 50
    include_all_tools: false
    gateway_mode: false
    enable_resources: true
    enable_prompts: true
    component_public_only: false
    transports: ['http', 'stdio']
    scopes: ['read', 'write']
    # permission_callback: 'my_module.server_access:check'
```

Scopes are always limited by `access.allowed_scopes`. When no trusted override is present, `access.default_scopes` are used.

Set `transports` to limit which entrypoints (HTTP/STDIO) can run a profile; leave empty or omit to allow all transports.
Set `component_public_only` to expose only components explicitly marked as public.

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

## Write Submodules (197 tools across 29 submodules)

Enable submodules for the capabilities you need. Each submodule's tools are listed in its own `README.md`.

| Submodule | Tools | Description |
|-----------|------:|-------------|
| `mcp_tools_content` | 4 | Content CRUD (create, update, delete, publish) |
| `mcp_tools_structure` | 12 | Content types, fields, vocabularies, roles, permissions |
| `mcp_tools_users` | 5 | User accounts, roles, blocking |
| `mcp_tools_menus` | 5 | Menus and menu links |
| `mcp_tools_views` | 6 | Views creation and management |
| `mcp_tools_blocks` | 5 | Block placement and configuration |
| `mcp_tools_media` | 6 | Media types, uploads, entities |
| `mcp_tools_webform` | 7 | Webform management and submissions (requires `webform`) |
| `mcp_tools_theme` | 8 | Theme settings, enable/disable |
| `mcp_tools_layout_builder` | 9 | Layout sections, blocks, plugins (requires `layout_builder`) |
| `mcp_tools_recipes` | 6 | Drupal Recipes — apply requires `admin` scope (10.3+) |
| `mcp_tools_config` | 5 | Configuration diff, export, MCP change tracking, preview |
| `mcp_tools_paragraphs` | 6 | Paragraph types and fields (requires `paragraphs`) |
| `mcp_tools_moderation` | 6 | Content moderation workflows (requires `content_moderation`) |
| `mcp_tools_scheduler` | 5 | Scheduled publish/unpublish (requires `scheduler`) |
| `mcp_tools_metatag` | 5 | SEO meta tags (requires `metatag`) |
| `mcp_tools_image_styles` | 7 | Image styles and effects |
| `mcp_tools_cache` | 6 | Cache clear, invalidate, rebuild |
| `mcp_tools_cron` | 5 | Cron jobs and queues |
| `mcp_tools_ultimate_cron` | 6 | Ultimate Cron job management (requires `ultimate_cron`) |
| `mcp_tools_pathauto` | 6 | URL alias patterns (requires `pathauto`) |
| `mcp_tools_redirect` | 7 | URL redirects (requires `redirect`) |
| `mcp_tools_sitemap` | 7 | XML sitemap management (requires `simple_sitemap`) |
| `mcp_tools_search_api` | 8 | Search indexes and servers (requires `search_api`) |
| `mcp_tools_entity_clone` | 4 | Entity cloning (requires `entity_clone`) |
| `mcp_tools_analysis` | 8 | SEO, security, accessibility, performance audits |
| `mcp_tools_batch` | 6 | Bulk content operations (max 50 items/batch) |
| `mcp_tools_templates` | 5 | Site templates (blog, portfolio, business, docs) |
| `mcp_tools_migration` | 7 | CSV/JSON import and export (max 100 items) |

**Safety built in:** uid 1 protected, administrator role unassignable, system menus/views/themes guarded, dangerous permissions blocked, batch limits enforced, base64 uploads capped.

## Security

### Built-in Protections

- **Modular by default** - Enable only the submodules you need
- **Three-layer access control** - Modules, global toggle, connection scopes
- **Permission-based** - Each category has its own Drupal permission
- **Audit logging** - All write operations logged with user info
- **Read operation throttling** - Expensive read operations are rate-limited (broken links, content search)
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

## Usage

### Local (STDIO via Drush) — recommended

```bash
# Enable the transport.
drush en mcp_tools_stdio -y

# Run the MCP server over STDIO (Claude Desktop, Claude Code, etc).
drush mcp-tools:serve --uid=1

# With specific scopes (local only)
MCP_SCOPE=read,write drush mcp-tools:serve --uid=1
# or: drush mcp-tools:serve --uid=1 --scope=read,write
```

### Remote (HTTP) — experimental

```bash
# Enable the transport.
drush en mcp_tools_remote -y

# Create a read-only API key (shown once).
drush mcp-tools:remote-key-create --label="My Key" --scopes=read --ttl=86400
```

Configure your MCP client to use `/_mcp_tools` and send the key as `Authorization: Bearer …` or `X-MCP-Api-Key: …`.

Configure the endpoint at `/admin/config/services/mcp-tools/remote` (use "uid 1" for development or create a dedicated mcp_executor account; consider IP and Origin/Host allowlists).

### CLI Helpers

```bash
# List server profiles.
drush mcp:servers

# Apply the recommended development preset and enable bundles.
drush mcp:dev-profile

# Inspect a server profile and list components.
drush mcp:server-info --server=default --tools --resources --prompts

# Smoke-test server configuration and dependencies.
drush mcp:server-smoke --server=default

# Validate component registry definitions.
drush mcp:components-validate

# Scaffold a component module.
drush mcp:scaffold --machine-name=my_module --name="My MCP Module"
```

### Alternative: drupal/mcp_server

If you choose to use [MCP Server](https://www.drupal.org/project/mcp_server) instead of the built-in transports, it provides its own Drush commands (e.g. `drush mcp:server`).

To prepare MCP Server tool configs for MCP Tools, enable the optional bridge and sync:

```bash
drush en mcp_tools_mcp_server -y
drush mcp-tools:mcp-server-sync --enable-read
```

This intentionally does **not** replace the recommended default (`mcp_tools_stdio`). It is a compatibility option for when upstream `mcp_server` is stable.


## Contributing

Issues and merge requests: https://www.drupal.org/project/issues/mcp_tools

## License

GPL-2.0-or-later
