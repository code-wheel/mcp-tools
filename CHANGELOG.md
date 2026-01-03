# Changelog

All notable changes to the MCP Tools module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.0.0-alpha4] - 2026-01-03

### Added

- `mcp_tools_stdio` submodule to run an MCP server over STDIO via Drush (`drush mcp-tools:serve`).
- `mcp_tools_remote` submodule to expose an HTTP MCP endpoint at `/_mcp_tools` using API key authentication.
- MCP server bridge classes for exposing Tool API tools via the official `mcp/sdk`.
- Unit test to ensure every tool category has a corresponding permission, plus unit coverage for remote API key management.

### Fixed

- Added missing `mcp_tools use {category}` permissions so non-uid1 roles can be granted access to all tool categories.

## [1.0.0-alpha3] - 2026-01-03

### Added

- Kernel smoke coverage to instantiate all core tools (144) and ensure `access()` never throws.

### Changed

- CI tool registration check now enforces a minimum of 144 MCP Tools tools (core-only install).

### Fixed

- `SystemStatusService` no longer references non-existent `SystemManager::REQUIREMENT_INFO` (Drupal 10/11).

## [1.0.0-alpha2] - 2026-01-03

### Added

- Tool API plugin implementations for all MCP Tools (205 tools) using PHP attributes (`#[Tool(...)]`) and `Plugin/tool/Tool` discovery.
- `Drupal\\mcp_tools\\Tool\\McpToolsToolBase` wrapper to adapt legacy MCP Tools responses into Tool API `ExecutableResult` objects and enforce category permissions + MCP scopes.
- Access configuration hardening: `access.allowed_scopes` plus trust toggles for reading scopes from headers, query params, and environment variables.
- Webhook host allowlist via `webhooks.allowed_hosts`.
- Read-operation rate limiting helpers and expanded kernel coverage.
- Kernel coverage for Tool API discovery/registration.

### Changed

- Minimum requirements: Drupal `^10.3 || ^11`, PHP `>=8.3`.
- MCP Server is optional (only required when exposing tools over MCP).

### Fixed

- Drupal 11 / Symfony 7 test compatibility and Tool API listing compatibility.

### Security

- Scope override values are intersected with `access.allowed_scopes` to prevent accidental privilege escalation via scope injection.
- Configuration analysis and audit logging redact sensitive values by default.

## [1.0.0-alpha1] - 2025-01-02

### Added

#### Core Module (mcp_tools) - 23 read-only tools
- **Site Health:** GetSiteStatus, GetSystemStatus, CheckSecurityUpdates, CheckCronStatus, AnalyzeWatchdog, GetQueueStatus, GetFileSystemStatus
- **Content:** ListContentTypes, GetRecentContent, SearchContent, GetVocabularies, GetTerms, GetFiles, FindOrphanedFiles
- **Configuration:** GetConfigStatus, GetConfig, ListConfig
- **Users:** GetRoles, GetUsers, GetPermissions
- **Structure:** GetMenus, GetMenuTree

#### Core Services
- **AccessManager** - Three-layer access control (module-based, global read-only mode, connection scopes)
- **RateLimiter** - Per-client, per-operation-type rate limiting with configurable limits
- **AuditLogger** - Operation logging with sensitive data redaction
- **WebhookNotifier** - HMAC-signed webhook notifications for external systems

#### Admin UI
- Settings form at `/admin/config/services/mcp-tools`
- Status page at `/admin/config/services/mcp-tools/status`
- Configurable access control, rate limiting, and webhook settings

#### Write/Analysis Submodules - 182 tools across 29 submodules

| Submodule | Tools | Description |
|-----------|-------|-------------|
| **mcp_tools_content** | 4 | Content CRUD (create, update, delete, publish) |
| **mcp_tools_structure** | 12 | Content types, fields, taxonomy, roles, permissions |
| **mcp_tools_users** | 5 | User management (create, update, block, roles) |
| **mcp_tools_menus** | 5 | Menu management (menus and links) |
| **mcp_tools_views** | 6 | Views creation and management |
| **mcp_tools_blocks** | 5 | Block placement and configuration |
| **mcp_tools_media** | 6 | Media types, uploads, entities |
| **mcp_tools_webform** | 7 | Webform creation and submissions |
| **mcp_tools_theme** | 8 | Theme settings and management |
| **mcp_tools_layout_builder** | 9 | Layout Builder sections and blocks |
| **mcp_tools_recipes** | 6 | Drupal Recipes (10.3+) |
| **mcp_tools_config** | 5 | Configuration export and tracking |
| **mcp_tools_paragraphs** | 6 | Paragraphs type and field management |
| **mcp_tools_moderation** | 6 | Content Moderation workflows and states |
| **mcp_tools_scheduler** | 5 | Scheduled publish/unpublish (contrib) |
| **mcp_tools_metatag** | 5 | SEO meta tags management (contrib) |
| **mcp_tools_image_styles** | 7 | Image styles and effects |
| **mcp_tools_cache** | 6 | Cache management and invalidation |
| **mcp_tools_cron** | 5 | Cron execution and queue processing |
| **mcp_tools_ultimate_cron** | 6 | Ultimate Cron job management (contrib) |
| **mcp_tools_pathauto** | 6 | URL alias patterns (contrib) |
| **mcp_tools_redirect** | 7 | URL redirects (contrib) |
| **mcp_tools_sitemap** | 7 | XML sitemap management (contrib) |
| **mcp_tools_search_api** | 8 | Search API index management (contrib) |
| **mcp_tools_entity_clone** | 4 | Entity cloning (contrib) |
| **mcp_tools_analysis** | 8 | Site analysis (SEO, a11y, security, performance) |
| **mcp_tools_batch** | 6 | Bulk operations (create, update, delete, publish) |
| **mcp_tools_templates** | 5 | Site templates (blog, portfolio, business, docs) |
| **mcp_tools_migration** | 7 | Content import/export (CSV, JSON) |

### Security

- **Three-layer access control** - Modules, global toggle, connection scopes
- **Rate limiting** - Configurable per-minute/hour limits by operation type (now includes admin operations)
- **Audit logging** - All operations logged with user info
- **SSRF protection** - Webhook URLs validated against private IPs and cloud metadata services
- **Role escalation protection** - Pattern-based blocking of admin/super roles
- **Import field protection** - Protected fields (uid, nid, moderation_state, etc.) cannot be set via import
- **Menu URI validation** - Strict scheme whitelist prevents javascript:/data: XSS attacks
- **Secure session identification** - Rate limiter uses system-level identifiers resistant to spoofing
- **Sensitive data redaction** - Passwords and secrets never logged or sent
- **Protected entities** - uid 1, administrator role, system menus, core views
- **Dangerous permissions blocked** - Cannot grant admin permissions via MCP
- **Webhook signatures** - HMAC-SHA256 signed webhook payloads

### Testing

- Unit tests for AccessManager, RateLimiter, AuditLogger
- Kernel tests for access control and rate limiting integration
- Security-focused tests for bypass prevention

---

## Tool Count Summary

| Category | Tools |
|----------|-------|
| Base module (read-only) | 22 |
| mcp_tools_content | 4 |
| mcp_tools_structure | 12 |
| mcp_tools_users | 5 |
| mcp_tools_menus | 5 |
| mcp_tools_views | 6 |
| mcp_tools_blocks | 5 |
| mcp_tools_media | 6 |
| mcp_tools_webform | 7 |
| mcp_tools_theme | 8 |
| mcp_tools_layout_builder | 9 |
| mcp_tools_recipes | 6 |
| mcp_tools_config | 5 |
| mcp_tools_paragraphs | 6 |
| mcp_tools_moderation | 6 |
| mcp_tools_scheduler | 5 |
| mcp_tools_metatag | 5 |
| mcp_tools_image_styles | 7 |
| mcp_tools_cache | 6 |
| mcp_tools_cron | 5 |
| mcp_tools_ultimate_cron | 6 |
| mcp_tools_pathauto | 6 |
| mcp_tools_redirect | 7 |
| mcp_tools_sitemap | 7 |
| mcp_tools_search_api | 8 |
| mcp_tools_entity_clone | 4 |
| mcp_tools_analysis | 8 |
| mcp_tools_batch | 6 |
| mcp_tools_templates | 5 |
| mcp_tools_migration | 7 |
| **Total** | **205** |
