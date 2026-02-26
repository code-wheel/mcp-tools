# Changelog

All notable changes to the MCP Tools module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.0.0-beta3] - 2026-02-25

### Fixed

- **Frozen timestamps in server mode**: `getRequestTime()` returns the process start time in long-running `drush mcp-tools:serve` sessions, causing all entities to share the same timestamp. Replaced with `getCurrentTime()` across 7 services: ContentService, MediaService, BatchService, TaxonomyManagementService, ModerationService, SchedulerService, and DrupalClock ([#3575317](https://www.drupal.org/project/issues/mcp_tools/3575317), reported by [guillaumeg](https://www.drupal.org/u/guillaumeg))

## [1.0.0-beta2] - 2026-02-06

### Changed

- Bump dependencies: `mcp-error-codes ^1.2`, `mcp-schema-builder ^1.1`, `mcp-tool-gateway ^1.1`
- Refactor DefaultToolErrorHandler to use McpError fluent builder
- Migrate all module services from hardcoded error strings to `ErrorCode::*` constants
- Fix list schema rejecting complex objects in batch tools

### Removed

- Remove dev-only `DRUPAL_ORG_DESCRIPTION.html` from public release

## [1.0.0-beta1] - 2026-01-09

### Added

- **DrupalToolProvider**: New adapter implementing `ToolProviderInterface` from mcp-tool-gateway for standardized tool discovery and execution patterns
- **Full MCP PHP ecosystem integration**: Now leverages 5 standalone Composer packages:
  - `code-wheel/mcp-error-codes: ^1.2` - ErrorCode constants + McpError fluent builder
  - `code-wheel/mcp-schema-builder: ^1.1` - TypeMapper, SchemaValidator, McpSchema presets
  - `code-wheel/mcp-tool-gateway: ^1.1` - ToolProviderInterface, middleware pipeline
  - `code-wheel/mcp-http-security: ^1.0` - API key validation, IP allowlist, scopes
  - `code-wheel/mcp-events: ^2.0` - Tool execution events

### Changed

- **DefaultToolErrorHandler**: Refactored to use `McpError` fluent builder for cleaner, more maintainable error responses
- **ErrorCode standardization**: All 24+ service files now use `ErrorCode::*` constants instead of hardcoded strings
- **ToolApiGateway**: Now uses DrupalToolProvider internally for consistent tool discovery

### Developer Experience

- **222 tools** across 34 submodules (up from 154)
- **741 unit tests** passing on Drupal 11 + PHP 8.4
- **Full CI pipeline** with Drupal 10.3 and 11.0 matrix testing

## [1.0.0-alpha26] - 2026-01-09

### Added

- **New standalone packages** for the PHP MCP ecosystem:
  - [code-wheel/mcp-error-codes](https://github.com/code-wheel/mcp-error-codes) v1.1 - Standardized error codes with helper methods
  - [code-wheel/mcp-events](https://github.com/code-wheel/mcp-events) v2.1 - Tool execution events with JsonSerializable support
- **Service interfaces**: Added `AccessManagerInterface`, `AuditLoggerInterface`, `RateLimiterInterface` for better testability
- **MenuManagementService**: Renamed from MenuService with proper interface
- **TaxonomyManagementService**: Renamed from TaxonomyService (in mcp_tools_structure) with proper interface

### Changed

- **External package dependencies**: Now uses external Composer packages instead of bundled code:
  - `code-wheel/mcp-error-codes: ^1.1` - Error code constants
  - `code-wheel/mcp-events: ^2.0` - Tool execution events (decoupled from mcp/sdk)
- **Service architecture**: Core services now implement interfaces for dependency injection
- **TemplateService**: Refactored for cleaner architecture with ComponentFactory
- **WriteAccessTrait**: Improved dry-run handling and confirmation flow

### Removed

- **Bundled event classes**: Removed `src/Mcp/Event/Tool*Event.php` (now in external package)
- **Duplicate services**: Removed duplicate TaxonomyService from mcp_tools_structure (uses core TaxonomyService)

### Fixed

- **McpToolsRemoteController**: Improved error handling and response formatting
- **SchedulerService**: Fixed service injection and method signatures

## [1.0.0-alpha25] - 2026-01-08

### Added

- **`mcp_tools_jsonapi` submodule**: Generic entity CRUD via JSON:API (6 new tools)
  - `mcp_jsonapi_discover_types` - Discover all available entity types/bundles
  - `mcp_jsonapi_get_entity` - Retrieve entity by UUID
  - `mcp_jsonapi_list_entities` - List entities with filtering and pagination
  - `mcp_jsonapi_create_entity` - Create any entity type
  - `mcp_jsonapi_update_entity` - Update entity by UUID
  - `mcp_jsonapi_delete_entity` - Delete entity by UUID
  - Configurable allowlist/blocklist for entity types
  - Security: user, shortcut, shortcut_set always blocked
  - Settings form at `/admin/config/services/mcp-tools/jsonapi`
- **`mcp_search_api_search` tool**: Search content via Search API indexes with keywords, filters, and pagination (added to `mcp_tools_search_api`)
- **`mcp:dev-profile` command**: One-command setup for development - applies development preset and enables recommended submodules
- **Grouped submodule display**: Status page and `mcp:status` now show all 34 submodules grouped by category (core-only, contrib-dependent, infrastructure)

### Security

- **UserService**: Added admin role protection to `blockUser()` - users with administrator role cannot be blocked via MCP
- **UserService**: Added input validation (username max 60 chars, email max 254 chars, email format validation)
- **RoleService**: Expanded `DANGEROUS_PERMISSIONS` from 10 to 19 entries including `administer blocks`, `administer views`, `create url aliases`
- **MenuService**: Enhanced URI validation to decode URL-encoded characters and prevent bypass attempts (e.g., `%6Aavascript:`)

### Fixed

- **ContentTypeService**: Fixed critical infinite recursion bug in `getEntityFieldManager()` that called itself instead of the service container
- **TaxonomyService**: Fixed N+1 query issues with new `getTermCountsByVocabulary()` and `batchLoadParents()` methods
- **mcp:status**: Tool counts now queried dynamically instead of hardcoded

### Changed

- **Bloat cleanup**: Removed Policy engine, Component system, telemetry module, workflows module, and example module
- **Config cleanup**: Removed `policy` section from `mcp_tools.settings.yml` and schema
- **Documentation**: Removed WordPress references, updated tool counts, removed COMPONENTS.md
- **Lifecycle consistency**: Added `lifecycle: experimental` to 9 submodules missing it
- **Package standardization**: Standardized `package: MCP Tools` across all submodules
- **Test updates**: Updated ToolSchemaKernelTest tool count (156 → 154), removed deleted modules from test lists

## [1.0.0-alpha24] - 2026-01-08

### Changed

- **Execution user configuration** for remote HTTP endpoint:
  - Added "Use site admin (uid 1)" checkbox for simple development setup
  - Added "Create MCP Executor Account" button to create dedicated service user/role
  - Removed automatic user creation on module install (now user-initiated)
  - Removed runtime block on uid 1 execution (now allowed via checkbox)
- Updated documentation to reflect execution user options

### Fixed

- Release workflow now installs `mcp-http-security` package before tests

## [1.0.0-alpha23] - 2026-01-08

### Fixed

- **Restored 4 unit tests** that were incorrectly marked as testing debt:
  - `RedirectServiceTest` - fixed assertion to match actual API (success=true, found=false)
  - `ContentTypeServiceTest` - fixed with proper DI for EntityFieldManagerInterface
  - `FieldServiceTest` - updated for new addField() signature and getFieldTypes() format
  - `WebformServiceTest` - fixed method names (listSubmissions→getSubmissions), removed dead tests
- `ContentTypeService` now uses proper dependency injection instead of static `\Drupal::service()` calls

## [1.0.0-alpha22] - 2026-01-07

### Added

- **New standalone package**: [code-wheel/mcp-http-security](https://github.com/code-wheel/mcp-http-security) extracted for the PHP MCP ecosystem
  - API key management with secure hashing (SHA-256 + pepper)
  - IP allowlisting with CIDR support (IPv4/IPv6)
  - Origin/hostname allowlisting with wildcard subdomains
  - PSR-15 SecurityMiddleware for any PHP framework
  - Multiple storage backends: Array, File, PDO
- **Drupal adapters** for the extracted package:
  - `DrupalStateStorage` - bridges Drupal State API to StorageInterface
  - `DrupalClock` - bridges Drupal TimeInterface to PSR-20 ClockInterface

### Changed

- `mcp_tools_remote` now delegates to `code-wheel/mcp-http-security` package
- Backward compatible: existing API keys continue to work unchanged
- Cleaner architecture with proper separation of concerns

### Dependencies

- Added `code-wheel/mcp-http-security: ^1.0` requirement

## [1.0.0-alpha21] - 2026-01-07

### Added

- **100% service test coverage**: All 48 services now have unit or kernel tests
  - New kernel tests for contrib-dependent services: EntityClone, Metatag, Pathauto, Scheduler, SearchApi, Sitemap, UltimateCron
  - Extended StructureServicesKernelTest with TaxonomyService coverage
  - Added kernel tests for Redirect, Webform services
  - ToolSchemaKernelTest expanded with MCP contract validations
- **Admin UI improvements**:
  - Permissions tab showing tool access by scope/category
  - Remote settings tab for HTTP endpoint configuration
- **Docker development environment**: `docker-compose.yml` + setup scripts for contributors
- **Strategic roadmap**: Community-driven roadmap items for post-adoption phase

### Fixed

- `date()` type errors in AnalyzePerformance and other analysis tools
- Unit tests no longer require contrib module dependencies

### Changed

- Clarified contrib module extraction plans (separate module, not submodule)

## [1.0.0-alpha20] - 2026-01-04

### Added

- **Configuration mode presets**: Development, Staging, Production modes with sensible defaults
  - Development: Full access, no rate limiting
  - Staging: Config-only mode, rate limited, audit logging
  - Production: Read-only mode, strict limits, full audit
- **Compound operations** in `mcp_tools_structure`:
  - `mcp_structure_scaffold_content_type` - Create content type + fields in one call
  - `mcp_structure_setup_taxonomy` - Create vocabulary + terms with hierarchy
- **Text format tools** in base module:
  - `mcp_list_text_formats` - List available text formats
  - `mcp_get_text_format` - Get format details including allowed HTML
- **Architecture documentation**: New `docs/ARCHITECTURE.md` with design patterns, security model

### Changed

- **idempotentHint** now set for read operations (TRUE for safe-to-retry ops)
- ROADMAP.md updated with completed P1 items

## [1.0.0-alpha19] - 2026-01-04

### Added

- **6 new schema discovery tools** in `mcp_tools_structure` for AI introspection:
  - `mcp_structure_list_content_types` - List all content types with field counts
  - `mcp_structure_get_content_type` - Get full field schema with types, cardinality, and allowed values
  - `mcp_structure_list_vocabularies` - List all vocabularies with term counts
  - `mcp_structure_get_vocabulary` - Get vocabulary details with terms and hierarchy
  - `mcp_structure_list_roles` - List all roles with permission/user counts
  - `mcp_structure_get_role_permissions` - Get permissions grouped by provider module
- **`destructive: TRUE` flag** added to 6 more tools: DisableTheme, DisableView, DisableJob, DisableLayoutBuilder, CancelSchedule, RevokePermissions (38 total destructive tools)
- **Remediation hints** added to 32 error messages across 12 services, guiding Claude to the correct discovery tools

### Changed

- **181 tool files updated** with rich, meaningful descriptions for all inputs and outputs
- All empty `TranslatableMarkup('')` entries replaced with helpful descriptions explaining:
  - Expected formats and valid values
  - Field type documentation (entity_reference, text_with_summary, etc.)
  - When to use each tool and relationships between tools
- ROADMAP.md cleaned up and simplified, removing completed phases

### Fixed

- Error messages now consistently reference discovery tools (e.g., "Content type 'blog' not found. Use mcp_structure_list_content_types to see available types.")

## [1.0.0-alpha18] - 2026-01-04

### Added

- Expanded unit test coverage for analysis/config/templates/batch/migration services to improve reliability and raise overall coverage.

### Changed

- Batch and migration operations now create entities via injected storages (instead of static `::create()` calls) to improve testability and consistency.

### Fixed

- Template application now logs audit events with the correct parameters (avoids runtime errors when audit logging is enabled).

## [1.0.0-alpha17] - 2026-01-04

### Added

- Optional Origin/Host allowlist for the remote HTTP endpoint (`mcp_tools_remote.settings.allowed_origins`) as defense-in-depth against DNS rebinding.
- JS MCP SDK STDIO smoke test (`scripts/mcp_js_sdk_compat.mjs`) wired into CI to catch strict-client schema regressions early.
- HTTP transport E2E coverage for Origin/Host allowlist behavior.

## [1.0.0-alpha16] - 2026-01-04

### Added

- Regression coverage for tools with no inputs to ensure MCP JSON Schemas encode empty `properties` as `{}`.

### Changed

- STDIO usage docs/examples now include `--uid` and recommend a dedicated execution user for shared environments.

### Fixed

- Tools with no inputs now return an MCP `inputSchema.properties` value that encodes as an object (`{}`), improving compatibility with strict MCP clients (Codex/Claude Code).

## [1.0.0-alpha15] - 2026-01-04

### Added

- DrupalCI (`.gitlab-ci.yml`) configuration to test across a broader core/PHP matrix.
- `drush mcp-tools:remote-setup` to create a dedicated remote execution user/role and configure `mcp_tools_remote.settings.uid`.
- Expanded config preview support: role create/delete, grant/revoke permissions, delete content type, and delete field preview operations.
- `DRUPALCI.md` and local testing notes to make CI failures easier to triage.

### Changed

- Audit logs now include a per-tool-call correlation ID, transport, client identifier, and active scopes.
- Status page now shows the remote HTTP endpoint (when enabled) and warns on risky remote settings (uid 1, empty allowlist, include-all-tools).
- CI "full tool registration" now validates schema conversion for all tools (not just registration count).

### Fixed

- STDIO E2E sets a known baseline for read-only/config-only so local reruns are deterministic.

## [1.0.0-alpha14] - 2026-01-04

### Changed

- Remote HTTP transport now refuses to execute as uid 1 (runtime enforcement, not just UI validation).
- HTTP transport E2E now validates IP allowlist enforcement and runs as a dedicated service user with only the required `mcp_tools use …` permissions.
- Drupal.org description and README now highlight read-only default scopes and include recommended starter bundles.

## [1.0.0-alpha13] - 2026-01-04

### Added

- Remote HTTP hardening: optional IP allowlist (`mcp_tools_remote.settings.allowed_ips`) and API key TTL support (`drush mcp-tools:remote-key-create --ttl=...`).
- Kernel coverage to ensure all core tool definitions convert cleanly to MCP tool annotations + JSON schemas.
- HTTP transport E2E now validates config-only mode (config writes allowed, ops writes denied).

### Changed

- Default connection scopes are now read-only by default (`access.default_scopes: [read]`).
- Admin-scope tools are now declared as `ToolOperation::Trigger` so they can be gated by admin scope at the Tool API access layer (recipes/templates/config export).
- Rate limiting now ignores the client-provided `X-MCP-Client-Id` header by default; opt-in via `rate_limiting.trust_client_id_header`.

## [1.0.0-alpha12] - 2026-01-03

### Added

- MCP-scoped config-change tracking via a tool-call context and a `config.save/delete/rename` subscriber (enables `mcp_config_mcp_changes` to reflect real tool activity).
- Unit/kernel coverage for previously untested core services: Analysis (broken links), Recipes, and Structure (content types + fields).
- `codecov.yml` to enforce high patch coverage while overall coverage ramps up.

### Changed

- `mcp_analysis_broken_links` adds an optional `base_url` input for STDIO/CLI usage.
- Broken-link scanner now enforces the `allowed_hosts` allowlist and blocks redirects to non-allowlisted hosts.
- CI no longer runs Drupal 11 on PHP 8.3 (Composer now requires PHP 8.4+ for current Drupal 11 releases).

## [1.0.0-alpha11] - 2026-01-03

### Added

- Additional unit coverage for Tool API access gating, cron/cache/image styles, and moderation services.

### Changed

- Refactored multiple services to use dependency injection (improves testability and reduces static `\Drupal::*` usage).
- Entity creation in write services now uses storage `->create()` instead of static entity `::create()` helpers.

### Fixed

- Cron job discovery now uses `ModuleHandlerInterface::invokeAllWith()` (prevents calling a non-existent `getImplementations()` method).
- `mcp_tools_recipes` now registers `logger.channel.mcp_tools_recipes` so the service container compiles cleanly in kernel tests.

## [1.0.0-alpha10] - 2026-01-03

### Added

- Tool metadata lint test to prevent read/write operation regressions.
- Kernel coverage for config-only mode gating by write kind.

### Fixed

- `mcp_upload_file` now correctly declares a write operation (requires write scope).
- Status page now shows config-only mode status.

## [1.0.0-alpha9] - 2026-01-03

### Added

- Config-only mode (restrict write tools to configuration changes) with configurable allowed write kinds.

### Fixed

- Corrected multiple Tool API operation declarations so write tools are not incorrectly exposed as read operations.

### Security

- Enforced correct write-scope gating for tools that mutate configuration or operational state (e.g., block placement, pathauto alias generation, Search API indexing).

## [1.0.0-alpha8] - 2026-01-03

### Added

- Unit coverage for schema conversion, error formatting, config management previews/exports, and remote controller failure paths.
- Additional unit coverage for batch/migration helpers and watchdog message formatting.

### Changed

- Code coverage job now runs unit + kernel + functional suites and excludes contrib-dependent submodules (unless their dependencies are installed).
- Release workflow now runs submodule unit/kernel/functional tests (not just base module tests).

### Security

- Base64 uploads are capped and block dangerous executable extensions by default.
- Serialized watchdog/metatag parsing now enforces size limits to prevent memory exhaustion.

## [1.0.0-alpha7] - 2026-01-03

### Added

- End-to-end MCP transport checks (STDIO + HTTP) wired into CI and release workflows.

### Fixed

- `drush mcp-tools:serve` no longer writes human output to STDOUT (prevents corrupting the STDIO JSON-RPC stream).
- `mcp_tools_remote` HTTP transport now uses a persistent session store (prevents 404 "session not found" after `initialize`).
- Drush command registration moved to per-submodule `drush.services.yml` files (avoids runtime dependency on Drush and fixes command discovery).
- Tool execution output handling now tolerates incomplete Tool API output contexts (prevents MCP tool calls failing with "provided context ... is not valid").

## [1.0.0-alpha6] - 2026-01-03

### Added

- Unit coverage for `TemplateService` and `WebhookNotifier` (signature + redaction + SSRF blocking).

### Fixed

- Functional UI tests now grant `access administration pages` and assert anonymous access denial robustly across login/403 configurations.
- CI now installs Drupal dev dependencies with `--with-all-dependencies` to avoid Drupal 11 Composer lock conflicts (e.g. `sebastian/diff` vs PHPUnit).

## [1.0.0-alpha5] - 2026-01-03

### Added

- `mcp_tools_mcp_server` optional bridge submodule (depends on `drupal/mcp_server`) with a Drush sync command to generate MCP Server tool configs for MCP Tools.

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
