# MCP Tools Roadmap

> Batteries-included MCP tools for AI assistants working with Drupal sites.

## Project Vision

MCP Tools provides **curated, high-value tools** that solve real problems—not generic CRUD. Inspired by [Sentry MCP](https://docs.sentry.io/product/sentry-mcp/), which provides actionable debugging tools rather than raw API access.

**Ultimate goal:** Enable AI-powered Drupal site building where you can say "Create a blog with articles, categories, and an editor role" and it happens.

---

## Current State (v1.0-alpha20)

### What We Have

- **214 tools** - Feature complete (30 read + 184 write/analysis)
- **Strong security model** - Multi-layer access control, audit logging
- **Good CI/CD** - GitHub Actions + DrupalCI
- **Excellent documentation** - README, Architecture docs, per-submodule READMEs
- **Real users** - Momentum is building

### Tool Breakdown

- **28 read-only tools** in the base module for site introspection
- **182 write/analysis tools** across 29 submodules
- **6 new schema discovery tools** for AI introspection
- All tools have rich descriptions for LLM understanding
- 38 destructive operations properly annotated

See [CHANGELOG.md](CHANGELOG.md) for full tool listing by submodule.

### Module Organization

**Core Drupal only (21 modules)** - depend on `drupal:*` modules:
- cache, cron, batch, templates, users, analysis, remote, moderation, blocks, config, layout_builder, media, image_styles, migration, structure, recipes, theme, menus, views, stdio, content

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
mcp_tools/                           # Base module (28 read-only tools)
├── src/
│   ├── Plugin/tool/Tool/            # Tool API plugins
│   ├── Form/SettingsForm.php        # Admin UI
│   ├── Controller/StatusController.php
│   └── Service/
│       ├── AccessManager.php        # Three-layer access control
│       ├── RateLimiter.php          # Rate limiting for writes
│       ├── AuditLogger.php          # Shared audit logging
│       └── [services]
└── modules/                         # 29 optional submodules
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

## Testing Debt

Unit tests for services that depend on contrib modules need kernel/functional tests with proper module bootstrapping.

| Test | Module | Status |
|------|--------|--------|
| `ParagraphsServiceTest` | mcp_tools_paragraphs | Needs kernel test with paragraphs installed |
| `RedirectServiceTest` | mcp_tools_redirect | ✅ Restored as unit test |
| `ContentTypeServiceTest` | mcp_tools_structure | ✅ Restored as unit test (fixed DI) |
| `FieldServiceTest` | mcp_tools_structure | ✅ Restored as unit test |
| `WebformServiceTest` | mcp_tools_webform | ✅ Restored as unit test |

---

## Future Roadmap

### Immediate (P0) - Quality & Developer Experience

| Task | Description | Status |
|------|-------------|--------|
| Docker dev environment | Full `docker-compose.yml` for contributors | 🔲 Todo |
| Test layering | Separate unit/kernel/integration tests | 🔲 Todo |
| CI matrix for contrib | Test each contrib module independently | 🔲 Todo |
| Restore deleted tests | Kernel tests for contrib-dependent services | 🔲 Todo |
| E2E test expansion | Expand mcp_stdio_e2e.py / mcp_http_e2e.py coverage | 🔲 Todo |

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
| Contract tests | Verify tool schemas match expected output formats | 🔲 Todo |
| Failure mode testing | Test permission denied, rate limits, edge cases | 🔲 Todo |
| Improve error messages | Actionable guidance when tools fail | 🔲 Todo |
| Dry-run mode | Preview what a tool would do without executing | 🔲 Todo |
| Service consolidation | Merge small related services | Deferred |

### User Adoption (P3) - Building Momentum

| Task | Description | Status |
|------|-------------|--------|
| Publish use cases | Blog posts/videos showing real workflows | 🔲 Todo |
| Create demo site | Sandbox where people can try MCP Tools | 🔲 Todo |
| Collect testimonials | Real user stories for social proof | 🔲 Todo |
| DrupalCon talk | Present at DrupalCon / Drupal camps | 🔲 Todo |
| Usage telemetry | Optional anonymous stats (which tools are popular) | 🔲 Todo |

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

**Note:** The module is feature-complete at 154 tools. Focus is on stability, testing, and adoption - resist feature creep.

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

The following permissions cannot be granted via MCP:
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
