# MCP Tools Roadmap

> Batteries-included MCP tools for AI assistants working with Drupal sites.

## Project Vision

MCP Tools provides **curated, high-value tools** that solve real problems—not generic CRUD. Inspired by [Sentry MCP](https://docs.sentry.io/product/sentry-mcp/), which provides actionable debugging tools rather than raw API access.

**Ultimate goal:** Enable AI-powered Drupal site building where you can say "Create a blog with articles, categories, and an editor role" and it happens.

---

## Pre-Release Fixes (alpha25)

Issues identified via comprehensive security and UX/DX scan.

### Security Fixes

| Priority | Issue | File | Status |
|----------|-------|------|--------|
| MEDIUM | Path traversal - accepts absolute paths without canonicalization | `RecipesService.php:570` | ✅ Fixed |
| LOW | `unserialize()` usage | `WatchdogAnalyzer.php`, `AnalysisService.php` | ✅ Already mitigated |

### UX/DX Fixes

| Priority | Issue | File | Status |
|----------|-------|------|--------|
| HIGH | Permission mismatch `'administer mcp tools'` vs `'mcp_tools administer'` | `mcp_tools_jsonapi.routing.yml` | ✅ Fixed |
| MEDIUM | Config schema lacks min/max constraints for integers | `mcp_tools.schema.yml` | N/A - Drupal schema doesn't support; form validates |
| MEDIUM | Executor role auto-grants ALL permissions | `RemoteSettingsForm.php` | ✅ Fixed |
| LOW | Routing title inconsistency | `mcp_tools_jsonapi.routing.yml` | ✅ Fixed |

### Test Coverage Gaps

| Component | Gap | Status |
|-----------|-----|--------|
| JsonApiService | No unit tests | ✅ Added |
| SearchApiService.search() | No unit tests | ✅ Added |
| Tool Plugins (222) | 0% coverage | Deferred - covered by kernel smoke tests |
| Commands (4) | 0% coverage | Deferred - tested via E2E scripts |

### Second Audit Fixes (alpha26)

| Priority | Issue | File | Status |
|----------|-------|------|--------|
| CRITICAL | Missing `getReadAccessDenied()` method called in MediaService | `AccessManager.php` | ✅ Fixed |
| HIGH | Orphaned `enabled_categories` config (defined but unused) | `mcp_tools.settings.yml` | ✅ Removed |
| HIGH | Documentation wrong: 215 tools → 222, 33 submodules → 34 | `README.md`, `ROADMAP.md` | ✅ Fixed |
| MEDIUM | 5 submodules missing README.md | jsonapi, mcp_server, observability, remote, stdio | ✅ Added |

### Comprehensive Audit Fixes (alpha28)

| Priority | Issue | Status |
|----------|-------|--------|
| HIGH | WriteAccessTrait static `\Drupal::` calls | ✅ Fixed - replaced with exception if not injected |
| HIGH | ServerConfigRepository container injection | ✅ Documented as intentional for dynamic callbacks |
| HIGH | MCP infrastructure missing unit tests | ✅ Added 53 tests (ToolInputValidator, ToolApiGateway, ServerConfigRepository) |
| HIGH | Service unit tests missing | ✅ Added 27 tests (EntityCloneService, SiteBlueprintService) |
| MEDIUM | No centralized error codes | ✅ Created `ErrorCode.php` with 18 constants + helpers |

**New Files Created:**
- `src/Mcp/Error/ErrorCode.php` - Centralized error code constants
- `tests/src/Unit/Mcp/ToolInputValidatorTest.php` - 14 test cases
- `tests/src/Unit/Mcp/ToolApiGatewayTest.php` - 16 test cases
- `tests/src/Unit/Mcp/ServerConfigRepositoryTest.php` - 23 test cases
- `tests/src/Unit/Mcp/ErrorCodeTest.php` - 18 test cases
- `tests/src/Unit/Service/SiteBlueprintServiceTest.php` - 12 test cases
- `modules/mcp_tools_entity_clone/tests/src/Unit/Service/EntityCloneServiceTest.php` - 15 test cases
- `modules/mcp_tools_remote/tests/src/Unit/Form/RemoteSettingsFormTest.php` - 9 test cases
- `modules/mcp_tools_jsonapi/tests/src/Unit/Form/JsonApiSettingsFormTest.php` - 8 test cases

**Additional Refactoring:**
- `McpToolsRemoteController::handle()` - Extracted 7 helper methods: `performSecurityChecks()`, `loadServerConfig()`, `checkServerAccess()`, `resolveScopes()`, `resolveExecutionAccount()`, `executeRequest()`, `resolveServerParams()`
- `TemplateService` - Extracted `loadTemplate()` and `templateNotFoundError()` helper methods, then extracted `ComponentFactory` service (1324→829 lines)
- `AnalysisService` - Extracted 8 analyzer services: `LinkAnalyzer`, `ContentAuditor`, `SeoAnalyzer`, `SecurityAuditor`, `FieldAnalyzer`, `PerformanceAnalyzer`, `AccessibilityAnalyzer`, `DuplicateDetector` (1269→143 lines)
- `ConfigManagementService` - Extracted 3 services: `ConfigComparisonService`, `McpChangeTracker`, `OperationPreviewService` (1067→280 lines)
- `ModerationService::getWorkflows()` - Renamed to `listWorkflows()` for API consistency
- `SchedulerService::getScheduledContent()` - Added pagination offset support and metadata

**New Interfaces Created:**
- `AccessManagerInterface` - Access control contract
- `AuditLoggerInterface` - Audit logging contract
- `RateLimiterInterface` - Rate limiting contract

### Comprehensive Audit (alpha27+)

Full codebase audit covering code quality, dependencies, security, tests, and API consistency.

#### Security Status: ✅ CLEAN

No critical vulnerabilities. Strong security practices with multi-layer access control, SSRF protection, proper input validation, and audit logging.

#### Code Quality Issues

| Priority | Issue | Files | Status |
|----------|-------|-------|--------|
| HIGH | Duplicate service classes causing confusion | `TaxonomyService.php` (2), `MenuService.php` (2) | Deferred - different namespaces, no collision |
| HIGH | Static `\Drupal::` calls instead of DI | `WriteAccessTrait.php`, `RateLimiter.php`, `TaxonomyService.php` | ✅ Fixed (all three) |
| HIGH | Service container injection (anti-pattern) | `ServerConfigRepository.php` | ✅ Documented as intentional (dynamic callback resolution) |
| MEDIUM | Large services need refactoring (>700 lines) | `TemplateService.php` (1324→829), `AnalysisService.php` (1269→143), `ConfigManagementService.php` (1067→280) | ✅ Fixed |
| MEDIUM | Duplicate template validation pattern (3x) | `TemplateService.php` | ✅ Fixed - extracted `loadTemplate()` + `templateNotFoundError()` |
| MEDIUM | McpToolsRemoteController::handle() too complex (180+ lines) | `McpToolsRemoteController.php` | ✅ Fixed - extracted 7 focused helper methods |
| LOW | Missing interface definitions for core services | AccessManager, AuditLogger, RateLimiter | ✅ Fixed - interfaces created |

#### Test Coverage Gaps

| Component | Status |
|-----------|--------|
| ParagraphsService | ✅ Test recreated |
| EntityCloneService | ✅ Unit tests added (15 tests) |
| SiteBlueprintService | ✅ Unit tests added (12 tests) |
| ToolInputValidator | ✅ Unit tests added (14 tests) |
| ToolApiGateway | ✅ Unit tests added (16 tests) |
| ServerConfigRepository | ✅ Unit tests added (23 tests) |
| MetatagService, PathautoService, SchedulerService, SitemapService, UltimateCronService | No unit tests (contrib-dependent) |
| RemoteSettingsForm | ✅ Unit tests added (9 tests) |
| JsonApiSettingsForm | ✅ Unit tests added (8 tests) |
| McpToolsRemoteController | ✅ Unit tests added (25 tests) |
| Drush Commands (4) | No unit tests (covered by E2E) |

#### API Consistency Issues

| Priority | Issue | Files | Status |
|----------|-------|-------|--------|
| HIGH | CacheService returns unwrapped structure (no success/data) | `CacheService.php` | ✅ Fixed |
| HIGH | CronService returns unwrapped structure (no success/data) | `CronService.php` | ✅ Fixed |
| MEDIUM | No centralized error code constants | Multiple services | ✅ Created `ErrorCode.php` with 18 constants |
| MEDIUM | Method naming: `getWorkflows()` should be `listWorkflows()` | `ModerationService.php` | ✅ Fixed |
| MEDIUM | Inconsistent pagination: some methods lack offset | `SchedulerService.php` | ✅ Fixed - added offset + pagination metadata |
| LOW | Pagination metadata not always returned | Multiple services | Deferred |

#### Dependency Issues

| Priority | Issue | Status |
|----------|-------|--------|
| MEDIUM | Circular dependency: AccessManager ↔ RateLimiter ↔ AuditLogger | Managed via optional injection |
| LOW | Underutilized services in main container (drush-only) | Deferred |

---

## Current State (v1.0-alpha25)

### What We Have

- **222 tools** across the base module and 34 submodules
- **Strong security model** - Multi-layer access control, audit logging
- **Good CI/CD** - GitHub Actions + DrupalCI
- **Excellent documentation** - README, Architecture docs, per-submodule READMEs
- **Two transport options** - STDIO (local) and HTTP (remote/Docker)
- **Standalone security package** - `code-wheel/mcp-http-security` for PHP MCP ecosystem

### Tool Breakdown

- **25 read-only tools** in the base module for site introspection
- **197 write/analysis tools** across 34 submodules
- All tools have rich descriptions for LLM understanding
- 38 destructive operations properly annotated

See [CHANGELOG.md](CHANGELOG.md) for full tool listing by submodule.

### Module Organization

**Core Drupal only (23 modules)** - depend on `drupal:*` modules:
- cache, cron, batch, templates, users, analysis, remote, moderation, blocks, config, layout_builder, media, image_styles, migration, structure, recipes, theme, menus, views, stdio, content, observability, jsonapi

**Contrib dependencies (11 modules):**

| Module | Requires |
|--------|----------|
| `mcp_tools_paragraphs` | paragraphs |
| `mcp_tools_redirect` | redirect |
| `mcp_tools_webform` | webform |
| `mcp_tools_pathauto` | pathauto |
| `mcp_tools_metatag` | metatag |
| `mcp_tools_scheduler` | scheduler |
| `mcp_tools_search_api` | search_api |
| `mcp_tools_sitemap` | simple_sitemap |
| `mcp_tools_entity_clone` | entity_clone |
| `mcp_tools_ultimate_cron` | ultimate_cron |
| `mcp_tools_mcp_server` | mcp_server |

**Architecture Decision:** Keep granular submodules (not consolidated). This follows Drupal conventions - 1:1 mapping with contrib modules, minimal code loading, independent releases, easy discoverability.

---

## Architecture

### Granular Submodule Design

```
mcp_tools/                           # Base module (25 read-only tools)
├── src/
│   ├── Plugin/tool/Tool/            # Tool API plugins
│   ├── Form/SettingsForm.php        # Admin UI
│   ├── Controller/StatusController.php
│   └── Service/
│       ├── AccessManager.php        # Three-layer access control
│       ├── RateLimiter.php          # Rate limiting for writes
│       ├── AuditLogger.php          # Shared audit logging
│       └── [services]
└── modules/                         # 34 optional submodules
    ├── mcp_tools_content/           # Content CRUD
    ├── mcp_tools_structure/         # Content types, fields, roles, taxonomies
    ├── mcp_tools_users/             # User management
    ├── mcp_tools_views/             # Views creation
    ├── mcp_tools_blocks/            # Block placement
    ├── mcp_tools_media/             # Media management
    ├── mcp_tools_layout_builder/    # Layout Builder
    ├── mcp_tools_config/            # Config management
    ├── mcp_tools_analysis/          # Site analysis tools
    └── [20 more submodules...]
```

### Design Principles

1. **Services are decoupled** - Business logic in plain PHP services
2. **Tools are thin wrappers** - Tool API plugins just call services
3. **Granular enablement** - Users enable only what they need
4. **Three-layer access control** - Defense in depth
5. **Audit everything** - All writes logged with sanitization
6. **Protect critical entities** - uid 1, administrator, core config

---

## Testing Status

All 48 services now have unit or kernel tests. Contrib-dependent services use kernel tests with proper module bootstrapping.

| Test | Module | Status |
|------|--------|--------|
| `ParagraphsServiceTest` | mcp_tools_paragraphs | ✅ Kernel test |
| `RedirectServiceTest` | mcp_tools_redirect | ✅ Kernel test |
| `ContentTypeServiceTest` | mcp_tools_structure | ✅ Unit test (fixed DI) |
| `FieldServiceTest` | mcp_tools_structure | ✅ Unit test |
| `WebformServiceTest` | mcp_tools_webform | ✅ Kernel test |

---

## Future Roadmap

### Immediate (P0) - Quality & Developer Experience

| Task | Description | Status |
|------|-------------|--------|
| Docker dev environment | Full `docker-compose.yml` for contributors | ✅ Done |
| Test layering | Separate unit/kernel/integration tests | ✅ Done |
| CI matrix for contrib | Test each contrib module independently | ✅ Done |
| Restore deleted tests | Kernel tests for contrib-dependent services | ✅ Done |
| E2E test expansion | STDIO + HTTP E2E tests in CI | ✅ Done |

### Short-term (P1) - COMPLETED

| Task | Description | Status |
|------|-------------|--------|
| Configuration presets | `development`, `staging`, `production` modes | ✅ Done |
| Batch/compound operations | ScaffoldContentType, SetupTaxonomy | ✅ Done |
| `idempotentHint` annotation | Read ops marked idempotent | ✅ Done |
| Text Formats tools | ListTextFormats, GetTextFormat | ✅ Done |
| Architecture documentation | docs/ARCHITECTURE.md | ✅ Done |

### Medium-term (P2) - Stability & Polish

| Task | Description | Status |
|------|-------------|--------|
| Contract tests | Verify tool schemas match expected output formats | ✅ Done |
| Failure mode testing | Test permission denied, rate limits, edge cases | ✅ Done |
| Improve error messages | Actionable guidance when tools fail | ✅ Done |
| Dry-run mode | Preview what a tool would do without executing | ✅ Done |
| Service consolidation | Merge small related services | Deferred |

### User Adoption (P3) - Building Momentum

| Task | Description | Status |
|------|-------------|--------|
| Publish use cases | Blog posts/videos showing real workflows | ✅ Done |
| Create demo site | Sandbox where people can try MCP Tools | ✅ Done |
| Collect testimonials | Real user stories for social proof | ✅ Done |
| DrupalCon talk | Present at DrupalCon / Drupal camps | ✅ Done |
| Usage telemetry | Optional anonymous stats (which tools are popular) | ✅ Done |

### Community-Driven (Post-Adoption)

These items are **not planned** until there's demonstrated community interest. They will be prioritized based on issue queue requests and adoption metrics.

#### Additional Contrib Integrations

| Module | Tools | Status |
|--------|-------|--------|
| Commerce | Products, orders, carts, payments | Waiting for demand |
| ECA | Event-Condition-Action rules | Waiting for demand |
| Group | Group content, membership | Waiting for demand |
| Feeds | Import configuration | Waiting for demand |

#### AI-Specific Features

| Feature | Description | Status |
|---------|-------------|--------|
| Context summaries | `mcp_get_site_context` - compact site overview for LLM context windows | Waiting for demand |
| Guided workflows | Multi-step wizards (e.g., "setup blog" orchestration) | Waiting for demand |
| Schema introspection | Enhanced field type documentation for LLM understanding | Waiting for demand |

#### Architecture Changes

| Task | Description | Rationale |
|------|-------------|-----------|
| Contrib module extraction | Move contrib integrations to `drupal/mcp_tools_contrib` | **Not recommended** - current 1:1 submodule structure is correct Drupal pattern |
| Multi-site support | Manage multiple Drupal instances | Complex, limited demand - not planned |
| Submodule consolidation | Merge related modules | **Not recommended** - granular modules enable selective installation |

#### Standalone Package Extraction

The following components have been extracted as standalone Composer packages for the broader PHP MCP ecosystem:

| Package | Description | Status |
|---------|-------------|--------|
| [code-wheel/mcp-http-security](https://github.com/code-wheel/mcp-http-security) | Secure HTTP transport wrapper with API key auth, IP/Origin allowlisting, PSR-15 middleware | ✅ Released v1.0.1 |
| `drupal-tool-mcp-bridge` | Bridge Drupal's Tool API plugins to MCP server tools | Waiting for demand |

**Released in alpha22:**
- `code-wheel/mcp-http-security` - Framework-agnostic security for PHP MCP servers
  - API key management with secure hashing (SHA-256 + pepper)
  - Multiple storage backends: Array, File, PDO
  - IP allowlisting with CIDR support (IPv4/IPv6)
  - Origin/hostname allowlisting with wildcard subdomains
  - PSR-15 SecurityMiddleware
  - PSR-20 Clock support

**Integration:** `mcp_tools_remote` now delegates to the extracted package via Drupal adapters (`DrupalStateStorage`, `DrupalClock`).

**Note:** The module has a strong tool surface area; the next focus is full MCP spec compliance and extensibility (resources, prompts, multi-server, observability).

---

## MCP Spec Compliance (2025+)

Goal: Full MCP specification compliance with Drupal-native architecture and curated tooling.

### Phase 1 (P4) - MCP Spec Compliance (Complete)

| Task | Description | Status |
|------|-------------|--------|
| MCP 2025-06-18 alignment | Ensure HTTP/STDIO behaviors align with current MCP spec | ✅ Done |
| Resources support | First-class MCP resources alongside tools | ✅ Done |
| Prompts support | First-class MCP prompts alongside tools | ✅ Done |
| Multi-server config | Per-server config with scoped access and transport selection | ✅ Done |
| Transport permission callbacks | Server-wide gatekeeper hook for auth/ACL | ✅ Done |
| Gateway mode | Discover/info/execute to avoid tool list bloat | ✅ Done |
| Schema validation | Validate tool/resource/prompt I/O; add contract tests | ✅ Done |
| Error handling interface | Swappable error handler with standardized JSON-RPC mapping | ✅ Done |
| Observability hooks | Unified event emission with pluggable handlers | ✅ Done |
| CLI/Drush upgrades | List servers, inspect components, smoke-test endpoints | ✅ Done |

### Phase 2 (P5) - Scale + DX

| Task | Description | Status |
|------|-------------|--------|
| Server profiles | Preset server templates (dev/stage/prod) | ✅ Done |
| Observability module | Event emission with watchdog/syslog handlers | ✅ Done |
| Performance | Schema payload slimming, lazy loading | ✅ Done |
| Docs & SDK | Component authoring guide + templates | ✅ Done |

### Phase 3 (P6) - Resources & Prompts

| Task | Description | Status |
|------|-------------|--------|
| Context snapshots | Site blueprint + config drift summaries as resources | ✅ Done |
| MCP Resources | First-class MCP resources with registry | ✅ Done |
| MCP Prompts | First-class MCP prompts with registry | ✅ Done |

### Ongoing Strategy

- Keep granular submodules; simplify consumption with presets and scaffolds.
- Prioritize production readiness with compatibility, deprecation, and security defaults.
- Let user demand drive new features rather than speculative infrastructure.

### Phase 4 (P7) - Adoption & Authoring UX

| Task | Description | Status |
|------|-------------|--------|
| Component scaffolding | Drush generator + templates for tools | ✅ Done |
| Quickstart onboarding | "first tool in 5 minutes" guide | ✅ Done |
| Client integration pack | Ready `mcp.json` samples for Claude Desktop/Code, Cursor, VS Code | ✅ Done |
| Troubleshooting guide | Common errors, auth pitfalls, request/response examples | ✅ Done |
| Multi-transport profiles | Single server profile that can expose both HTTP + STDIO | ✅ Done |

### Phase 5 (P8) - Beta Readiness & Release Discipline (CURRENT)

| Task | Description | Status |
|------|-------------|--------|
| Compatibility matrix | Document supported Drupal core/PHP/Tool API versions | ✅ Done (README) |
| Security defaults review | Remote transport hardening checklist and warnings | ✅ Done (README) |
| Lifecycle consistency | All submodules marked experimental | ✅ Done |
| Documentation accuracy | Tool counts, submodule counts accurate | ✅ Done |
| Release policy | Define alpha/beta/stable criteria and deprecation policy | Planned |
| Update safety | Upgrade hooks + regression checks for config changes | Planned |

### Future (Post-Beta)

| Task | Description | Status |
|------|-------------|--------|
| HTTP session store backends | Database/key-value store for Streamable HTTP sessions | Future |
| Multi-webhead hosting | Support for managed platforms without shared filesystem | Future |

### Phase 6 (P9) - Ecosystem Alignment (Ongoing)

| Task | Description | Status |
|------|-------------|--------|
| Contrib authoring guide | How modules register MCP components via Tool API/registry | Planned |
| Drupal AI alignment | Interop guidance with Drupal AI/Tool API ecosystem | Planned |
| Module registry | Public index of MCP-enabled contrib modules | Planned |

### Existing Infrastructure Submodules

| Module | Purpose | Status |
|--------|---------|--------|
| `mcp_tools_remote` | HTTP transport with API key auth | ✅ Implemented |
| `mcp_tools_stdio` | STDIO transport for local MCP clients | ✅ Implemented |
| `mcp_tools_observability` | Event emission + watchdog handlers | ✅ Implemented |

---

## Guardrails & Security

### Access Control Summary

| Layer | Implementation |
|-------|----------------|
| **Module-based** | Only enabled submodules expose tools |
| **Global toggle** | Read-only mode blocks all writes |
| **Connection scopes** | Per-connection access levels |
| **Permissions** | Drupal permission per category |
| **Audit logging** | All operations logged |
| **Entity protection** | uid 1, administrator, core entities protected |

### Protected Entities

| Entity Type | Protection |
|-------------|------------|
| Users | uid 1 cannot be modified |
| Roles | administrator role cannot be assigned via MCP |
| Permissions | Dangerous permissions blocked |
| Menus | System menus (admin, main, footer) protected |
| Views | Core views protected from deletion |

### Dangerous Permissions Blocked

The following permissions cannot be granted via MCP (prevents privilege escalation):
- `administer permissions`
- `administer users`
- `administer site configuration`
- `administer modules`
- `administer software updates`
- `administer themes`
- `administer menu`
- `administer blocks`
- `administer views`
- `administer url aliases`
- `bypass node access`
- `access all views`
- `synchronize configuration`
- `import configuration`
- `export configuration`
- `cancel account`
- `select account cancellation method`
- `translate interface`
- `create url aliases`

---

## Testing Strategy

### Test Layering

```
Unit Tests (no Drupal, no contrib)
├── Test pure PHP logic only
├── Mock ALL Drupal services
└── Run fast, no bootstrap

Kernel Tests (Drupal core only)
├── Test services with real Drupal APIs
├── Use drupal:* modules only
└── Run with minimal Drupal bootstrap

Integration Tests (per-contrib)
├── Separate test suite per contrib module
├── Only run when that contrib is installed
├── Use @requires module annotation
```

### CI Matrix Approach

```yaml
jobs:
  test-core:
    # Unit tests + kernel tests for core-only submodules

  test-paragraphs:
    # Install paragraphs, run mcp_tools_paragraphs tests

  test-webform:
    # Install webform, run mcp_tools_webform tests

  # ... one job per contrib integration
```

### Example Test Structure

```php
/**
 * @requires module paragraphs
 * @group mcp_tools_paragraphs
 */
class ParagraphsServiceKernelTest extends KernelTestBase {
  protected static $modules = ['paragraphs', 'mcp_tools', 'mcp_tools_paragraphs'];
  // ...
}
```

---

## Development Environment

### Local Development

**DDEV (recommended):**
```bash
ddev start
ddev composer install
ddev drush si minimal -y
ddev drush en mcp_tools -y
ddev test  # Run all tests
```

**Docker Compose:**
```bash
docker compose up -d
docker compose exec drupal drush si minimal -y
docker compose exec drupal drush en mcp_tools -y
```

See `docker-compose.yml` for the full local development environment.

---

## References

- [MCP Specification](https://modelcontextprotocol.io/)
- [Sentry MCP](https://docs.sentry.io/product/sentry-mcp/) - Inspiration
- [mcp_server](https://www.drupal.org/project/mcp_server) - Transport layer
- [Tool API](https://www.drupal.org/project/tool) - Plugin framework
- [Drupal Entity API](https://www.drupal.org/docs/drupal-apis/entity-api)
