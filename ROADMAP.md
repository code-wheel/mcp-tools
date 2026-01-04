# MCP Tools Roadmap

> Batteries-included MCP tools for AI assistants working with Drupal sites.

## Project Vision

MCP Tools provides **curated, high-value tools** that solve real problems—not generic CRUD. Inspired by [Sentry MCP](https://docs.sentry.io/product/sentry-mcp/), which provides actionable debugging tools rather than raw API access.

**Ultimate goal:** Enable AI-powered Drupal site building where you can say "Create a blog with articles, categories, and an editor role" and it happens.

---

## Current State (v1.0-alpha20)

### 214 Tools Total (30 Read + 184 Write/Analysis)

- **28 read-only tools** in the base module for site introspection
- **182 write/analysis tools** across 29 submodules
- **6 new schema discovery tools** for AI introspection
- All tools have rich descriptions for LLM understanding
- 38 destructive operations properly annotated

See [CHANGELOG.md](CHANGELOG.md) for full tool listing by submodule.

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

## Future Roadmap

### Short-term (P1) - COMPLETED

| Task | Description | Status |
|------|-------------|--------|
| Configuration presets | `development`, `staging`, `production` modes | ✅ Done |
| Batch/compound operations | ScaffoldContentType, SetupTaxonomy | ✅ Done |
| `idempotentHint` annotation | Read ops marked idempotent | ✅ Done |
| Text Formats tools | ListTextFormats, GetTextFormat | ✅ Done |
| Architecture documentation | docs/ARCHITECTURE.md | ✅ Done |

### Medium-term (P2)

| Task | Description | Status |
|------|-------------|--------|
| Service consolidation | Merge small related services | Deferred |
| Additional testing | Integration tests, edge cases | Ongoing |

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
| Contrib module extraction | Move contrib integrations (paragraphs, webform, metatag, pathauto, redirect, scheduler, search_api, simple_sitemap, ultimate_cron, entity_clone) to a **separate Drupal module** `drupal/mcp_tools_contrib` | Separate repo, separate CI, independent releases. Keeps `mcp_tools` focused on core Drupal functionality. **Only worthwhile if adoption justifies the maintenance overhead of two packages.** |
| Multi-site support | Manage multiple Drupal instances | Complex, limited demand - not planned |
| Submodule consolidation | Merge SEO modules | Not planned - current structure mirrors contrib modules |

**Note:** The module is feature-complete at 214 tools. Future work will focus on bug fixes, compatibility updates, and community-requested features.

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

## References

- [MCP Specification](https://modelcontextprotocol.io/)
- [Sentry MCP](https://docs.sentry.io/product/sentry-mcp/) - Inspiration
- [mcp_server](https://www.drupal.org/project/mcp_server) - Transport layer
- [Tool API](https://www.drupal.org/project/tool) - Plugin framework
- [Drupal Entity API](https://www.drupal.org/docs/drupal-apis/entity-api)
