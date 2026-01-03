# MCP Tools Roadmap

> Batteries-included MCP tools for AI assistants working with Drupal sites.

## Project Vision

MCP Tools provides **curated, high-value tools** that solve real problems—not generic CRUD. Inspired by [Sentry MCP](https://docs.sentry.io/product/sentry-mcp/), which provides actionable debugging tools rather than raw API access.

**Ultimate goal:** Enable AI-powered Drupal site building where you can say "Create a blog with articles, categories, and an editor role" and it happens.

---

## Current State (v1.0-dev)

### 204 Tools Total (22 Read + 182 Write/Analysis)

#### Read Tools (mcp_tools) - 22 tools

| Category | Tools | Status |
|----------|-------|--------|
| **Site Health** | GetSiteStatus, GetSystemStatus, CheckSecurityUpdates, CheckCronStatus, AnalyzeWatchdog, GetQueueStatus, GetFileSystemStatus | ✅ Done |
| **Content** | ListContentTypes, GetRecentContent, SearchContent, GetVocabularies, GetTerms, GetFiles, FindOrphanedFiles | ✅ Done |
| **Config** | GetConfigStatus, GetConfig, ListConfig | ✅ Done |
| **Users** | GetRoles, GetUsers, GetPermissions | ✅ Done |
| **Structure** | GetMenus, GetMenuTree | ✅ Done |

#### Write/Analysis Submodules - 182 tools across 29 submodules

| Submodule | Tools | Status |
|-----------|-------|--------|
| **mcp_tools_content** | CreateContent, UpdateContent, DeleteContent, PublishContent | ✅ Done |
| **mcp_tools_structure** | CreateContentType, DeleteContentType, AddField, DeleteField, ListFieldTypes, CreateVocabulary, CreateTerm, CreateTerms, CreateRole, DeleteRole, GrantPermissions, RevokePermissions | ✅ Done |
| **mcp_tools_users** | CreateUser, UpdateUser, BlockUser, ActivateUser, AssignUserRoles | ✅ Done |
| **mcp_tools_menus** | CreateMenu, DeleteMenu, AddMenuLink, UpdateMenuLink, DeleteMenuLink | ✅ Done |
| **mcp_tools_views** | CreateView, CreateContentListView, DeleteView, AddViewDisplay, EnableView, DisableView | ✅ Done |
| **mcp_tools_blocks** | PlaceBlock, RemoveBlock, ConfigureBlock, ListAvailableBlocks, ListRegions | ✅ Done |
| **mcp_tools_media** | CreateMediaType, DeleteMediaType, UploadFile, CreateMedia, DeleteMedia, ListMediaTypes | ✅ Done |
| **mcp_tools_webform** | ListWebforms, GetWebform, GetSubmissions, CreateWebform, UpdateWebform, DeleteWebform, DeleteSubmission | ✅ Done |
| **mcp_tools_theme** | GetActiveTheme, ListThemes, SetDefaultTheme, SetAdminTheme, GetThemeSettings, UpdateThemeSettings, EnableTheme, DisableTheme | ✅ Done |
| **mcp_tools_layout_builder** | EnableLayoutBuilder, DisableLayoutBuilder, AllowCustomLayouts, GetLayout, AddSection, RemoveSection, AddBlock, RemoveBlock, ListLayoutPlugins | ✅ Done |
| **mcp_tools_recipes** | ListRecipes, GetRecipe, ValidateRecipe, ApplyRecipe, GetAppliedRecipes, CreateRecipe | ✅ Done |
| **mcp_tools_config** | GetConfigChanges, ExportConfig, GetMcpChanges, GetConfigDiff, PreviewOperation | ✅ Done |
| **mcp_tools_paragraphs** | ListParagraphTypes, GetParagraphType, CreateParagraphType, DeleteParagraphType, AddParagraphField, DeleteParagraphField | ✅ Done |
| **mcp_tools_moderation** | GetWorkflows, GetWorkflow, GetState, SetState, GetHistory, GetContentByState | ✅ Done |
| **mcp_tools_scheduler** | GetScheduled, SchedulePublish, ScheduleUnpublish, CancelSchedule, GetSchedule | ✅ Done |
| **mcp_tools_metatag** | GetDefaults, GetEntityMetatags, SetEntityMetatags, ListGroups, ListTags | ✅ Done |
| **mcp_tools_image_styles** | ListStyles, GetStyle, CreateStyle, DeleteStyle, AddEffect, RemoveEffect, ListEffects | ✅ Done |
| **mcp_tools_cache** | GetStatus, ClearAll, ClearBin, InvalidateTags, ClearEntity, Rebuild | ✅ Done |
| **mcp_tools_cron** | GetStatus, Run, RunQueue, UpdateSettings, ResetKey | ✅ Done |
| **mcp_tools_ultimate_cron** | ListJobs, GetJob, RunJob, EnableJob, DisableJob, GetJobLogs | ✅ Done |
| **mcp_tools_pathauto** | ListPatterns, GetPattern, CreatePattern, UpdatePattern, DeletePattern, GenerateAliases | ✅ Done |
| **mcp_tools_redirect** | ListRedirects, GetRedirect, CreateRedirect, UpdateRedirect, DeleteRedirect, FindBySource, ImportRedirects | ✅ Done |
| **mcp_tools_sitemap** | GetStatus, ListSitemaps, GetSettings, UpdateSettings, Regenerate, GetEntitySettings, SetEntitySettings | ✅ Done |
| **mcp_tools_search_api** | ListIndexes, GetIndex, GetIndexStatus, ReindexIndex, IndexItems, ClearIndex, ListServers, GetServer | ✅ Done |
| **mcp_tools_entity_clone** | CloneEntity, CloneWithReferences, GetCloneableTypes, GetCloneSettings | ✅ Done |
| **mcp_tools_analysis** | FindBrokenLinks, ContentAudit, AnalyzeSeo, SecurityAudit, FindUnusedFields, AnalyzePerformance, CheckAccessibility, FindDuplicateContent | ✅ Done |
| **mcp_tools_batch** | CreateMultipleContent, UpdateMultipleContent, DeleteMultipleContent, PublishMultiple, AssignRoleToUsers, CreateMultipleTerms | ✅ Done |
| **mcp_tools_templates** | ListTemplates, GetTemplate, ApplyTemplate, PreviewTemplate, ExportAsTemplate | ✅ Done |
| **mcp_tools_migration** | ImportFromCsv, ImportFromJson, ValidateImport, GetFieldMapping, ExportToCsv, ExportToJson, GetImportStatus | ✅ Done |

---

## Architecture

### Granular Submodule Design

```
mcp_tools/                           # Base module (22 read-only tools)
├── src/
│   ├── Plugin/tool/Tool/            # Tool API plugins (read-only tools)
│   ├── Form/SettingsForm.php        # Admin UI
│   ├── Controller/StatusController.php  # Status page
│   └── Service/
│       ├── AccessManager.php        # Three-layer access control
│       ├── RateLimiter.php          # Rate limiting for writes
│       ├── AuditLogger.php          # Shared audit logging
│       ├── WebhookNotifier.php      # Webhook notifications
│       ├── ErrorFormatter.php       # Standardized error responses
│       └── [11 read services]
└── modules/
    ├── mcp_tools_content/           # 4 tools
    ├── mcp_tools_structure/         # 12 tools (content types, fields, roles)
    ├── mcp_tools_users/             # 5 tools
    ├── mcp_tools_menus/             # 5 tools
    ├── mcp_tools_views/             # 6 tools
    ├── mcp_tools_blocks/            # 5 tools
    ├── mcp_tools_media/             # 6 tools
    ├── mcp_tools_webform/           # 7 tools
    ├── mcp_tools_theme/             # 8 tools (theme settings)
    ├── mcp_tools_layout_builder/    # 9 tools (Layout Builder)
    ├── mcp_tools_recipes/           # 6 tools (Drupal Recipes)
    ├── mcp_tools_config/            # 5 tools (config management)
    ├── mcp_tools_paragraphs/        # 6 tools (Paragraphs)
    ├── mcp_tools_moderation/        # 6 tools (Content Moderation)
    ├── mcp_tools_scheduler/         # 5 tools (Scheduler contrib)
    ├── mcp_tools_metatag/           # 5 tools (Metatag contrib)
    ├── mcp_tools_image_styles/      # 7 tools (Image Styles)
    ├── mcp_tools_cache/             # 6 tools (Cache management)
    ├── mcp_tools_cron/              # 5 tools (Cron management)
    ├── mcp_tools_ultimate_cron/     # 6 tools (Ultimate Cron contrib)
    ├── mcp_tools_pathauto/          # 6 tools (Pathauto contrib)
    ├── mcp_tools_redirect/          # 7 tools (Redirect contrib)
    ├── mcp_tools_sitemap/           # 7 tools (Simple XML Sitemap)
    ├── mcp_tools_search_api/        # 8 tools (Search API)
    ├── mcp_tools_entity_clone/      # 4 tools (Entity Clone)
    ├── mcp_tools_analysis/          # 8 tools (Site analysis)
    ├── mcp_tools_batch/             # 6 tools (Bulk operations)
    ├── mcp_tools_templates/         # 5 tools (Site templates)
    └── mcp_tools_migration/         # 7 tools (Content migration)
```

### Access Control Layers

1. **Module-based**: Only enabled submodules expose tools
2. **Global read-only mode**: Site-wide toggle to block all writes
3. **Connection scopes**: Per-connection access (read, write, admin)

### Design Principles

1. **Services are decoupled** - Business logic in plain PHP services
2. **Tools are thin wrappers** - Tool API plugins just call services
3. **Granular enablement** - Users enable only what they need
4. **Three-layer access control** - Defense in depth
5. **Audit everything** - All writes logged with sanitization
6. **Protect critical entities** - uid 1, administrator, core config

---

## Completed Phases

### Phase 1-3: Core Write Operations ✅

All basic write operations implemented across granular submodules.

**Supported field types (18):**
- `string` / `string_long` - Text fields
- `text` / `text_long` / `text_with_summary` - Formatted text
- `integer` / `decimal` / `float` - Numbers
- `boolean` - Checkbox
- `datetime` - Date/time
- `entity_reference` - References to other entities
- `image` / `file` - Media
- `link` - URLs
- `list_string` / `list_integer` / `list_float` - Select lists
- `email` / `telephone` - Contact fields

### Phase 4: Advanced Integrations ✅

| Integration | Status |
|-------------|--------|
| Views creation | ✅ mcp_tools_views |
| Block placement | ✅ mcp_tools_blocks |
| Media upload | ✅ mcp_tools_media |
| Webform integration | ✅ mcp_tools_webform |
| Unit tests | ✅ PHPUnit tests for all services |

### Phase 5: Theme, Layout Builder, Recipes ✅

| Integration | Status |
|-------------|--------|
| Theme settings management | ✅ mcp_tools_theme (8 tools) |
| Layout Builder | ✅ mcp_tools_layout_builder (9 tools) |
| Drupal Recipes | ✅ mcp_tools_recipes (6 tools) |

### Phase 6: Production Hardening ✅

| Feature | Status |
|---------|--------|
| Admin settings UI | ✅ `/admin/config/services/mcp-tools` |
| Rate limiting service | ✅ Per-client, per-operation-type limits |
| Webhook notifications | ✅ HMAC-signed payloads to external systems |
| Configuration management | ✅ mcp_tools_config (5 tools) |
| Kernel tests | ✅ Access control, rate limiting, security |

---

## Future Roadmap

### Phase 7: Polish & Developer Experience (Current)

**Goal:** Improve usability and developer experience before release

| Task | Description | Priority | Status |
|------|-------------|----------|--------|
| Submodule documentation | Add README.md to each submodule with examples | P0 | ✅ Done |
| Drush status command | `drush mcp:status`, `drush mcp:tools`, `drush mcp:reset-limits` | P0 | ✅ Done |
| Improved error messages | ErrorFormatter service with standardized responses | P1 | ✅ Done |
| Tool discovery hints | Help AI understand tool relationships | P1 | Pending |
| Functional tests | Browser tests for admin UI | P2 | Pending |

### Phase 8: Core Module Enhancements ✅

**Goal:** Complete coverage of essential Drupal core functionality

| Integration | Tools | Priority | Status |
|-------------|-------|----------|--------|
| **Content Moderation** | GetWorkflows, GetWorkflow, GetState, SetState, GetHistory, GetContentByState | P0 | ✅ Done |
| **Image Styles** | ListStyles, GetStyle, CreateStyle, DeleteStyle, AddEffect, RemoveEffect, ListEffects | P0 | ✅ Done |
| **Cache Management** | GetStatus, ClearAll, ClearBin, InvalidateTags, ClearEntity, Rebuild | P0 | ✅ Done |
| **Cron Management** | GetStatus, Run, RunQueue, UpdateSettings, ResetKey | P1 | ✅ Done |
| **Text Formats** | ListTextFormats, GetTextFormat | P2 | Pending |

### Phase 9: Popular Contrib Integrations ✅

**Goal:** Support the most popular contrib modules

| Integration | Tools | Priority | Status |
|-------------|-------|----------|--------|
| **Paragraphs** | ListParagraphTypes, CreateParagraphType, AddParagraphField, etc. | P0 | ✅ Done |
| **Scheduler** | GetScheduled, SchedulePublish, ScheduleUnpublish, CancelSchedule, GetSchedule | P0 | ✅ Done |
| **Metatag** | GetDefaults, GetEntityMetatags, SetEntityMetatags, ListGroups, ListTags | P0 | ✅ Done |
| **Ultimate Cron** | ListJobs, GetJob, RunJob, EnableJob, DisableJob, GetJobLogs | P1 | ✅ Done |
| **Pathauto** | ListPatterns, GetPattern, CreatePattern, UpdatePattern, DeletePattern, GenerateAliases | P1 | ✅ Done |
| **Redirect** | ListRedirects, GetRedirect, CreateRedirect, UpdateRedirect, DeleteRedirect, FindBySource, ImportRedirects | P1 | ✅ Done |
| **Simple XML Sitemap** | GetStatus, ListSitemaps, GetSettings, UpdateSettings, Regenerate, GetEntitySettings, SetEntitySettings | P2 | ✅ Done |
| **Search API** | ListIndexes, GetIndex, GetIndexStatus, ReindexIndex, IndexItems, ClearIndex, ListServers, GetServer | P2 | ✅ Done |
| **Entity Clone** | CloneEntity, CloneWithReferences, GetCloneableTypes, GetCloneSettings | P2 | ✅ Done |

### Phase 10: Analysis & Health Tools ✅

**Goal:** Higher-value analysis for site maintenance

| Tool | Description | Priority | Status |
|------|-------------|----------|--------|
| `mcp_analysis_broken_links` | Scan content for 404s | P0 | ✅ Done |
| `mcp_analysis_content_audit` | Find old/stale/orphaned content | P1 | ✅ Done |
| `mcp_analysis_seo` | Check meta tags, headings, alt text | P1 | ✅ Done |
| `mcp_analysis_security` | Permission review, exposed data check | P1 | ✅ Done |
| `mcp_analysis_unused_fields` | Fields with no data | P2 | ✅ Done |
| `mcp_analysis_performance` | Cache status, render times | P2 | ✅ Done |
| `mcp_analysis_accessibility` | Basic a11y checks | P2 | ✅ Done |
| `mcp_analysis_duplicates` | Detect similar content | P3 | ✅ Done |

### Phase 11: Enhanced Capabilities ✅

| Feature | Description | Priority | Status |
|---------|-------------|----------|--------|
| **Batch operations** | Bulk create/update content (50 item limit) | P1 | ✅ Done |
| **Template support** | Pre-built site configurations (blog, portfolio, business, docs) | P2 | ✅ Done |
| **Migration helpers** | CSV/JSON import/export (100 item limit) | P2 | ✅ Done |
| Multi-site support | Manage multiple Drupal instances | P3 | Pending |

### Phase 12: Ecosystem & Community (Current)

| Task | Description | Priority | Status |
|------|-------------|----------|--------|
| Drupal.org release | Project page, releases, documentation | P0 | Pending |
| Claude Code examples | Configuration and usage examples | P1 | ✅ Done |
| Demo video | Show "create a blog" workflow | P1 | Pending |
| Contribution guide | How to add new tools | P2 | ✅ Done |

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

## Example Use Cases

### Complete Site Building Session

```
User: "I need a portfolio site with projects and case studies"

AI calls:
1. mcp_structure_create_content_type(id: "project", label: "Project")
2. mcp_structure_add_field(bundle: "project", name: "client", type: "string")
3. mcp_structure_add_field(bundle: "project", name: "images", type: "image", cardinality: -1)
4. mcp_structure_create_vocabulary(id: "project_categories", label: "Project Categories")
5. mcp_structure_create_terms(vocabulary: "project_categories", terms: ["Web Design", "Branding", "UX"])
6. mcp_views_create_content_list(content_type: "project", label: "Projects")
7. mcp_blocks_place(block: "views_block:projects", region: "content")
```

### Role Setup with Safety

```
User: "Create an editor role with full content permissions"

AI calls:
1. mcp_structure_create_role(id: "editor", label: "Editor")
2. mcp_structure_grant_permissions(role: "editor", permissions: [
     "create article content",
     "edit any article content",
     "delete own article content"
   ])

Note: If user asks for "administer users" permission,
the tool will refuse with a clear error message.
```

### Webform Creation

```
User: "Create a contact form"

AI calls:
1. mcp_webform_create(id: "contact", title: "Contact Us", elements: {
     name: {type: "textfield", title: "Name", required: true},
     email: {type: "email", title: "Email", required: true},
     message: {type: "textarea", title: "Message", required: true}
   })
```

### Theme Customization

```
User: "Switch to the Olivero theme and update the logo"

AI calls:
1. mcp_theme_enable(theme: "olivero")
2. mcp_theme_set_default(theme: "olivero")
3. mcp_theme_update_settings(theme: "olivero", settings: {
     logo: {use_default: false, path: "sites/default/files/logo.svg"}
   })
```

### Layout Builder Setup

```
User: "Enable Layout Builder for Articles with a two-column layout"

AI calls:
1. mcp_layout_enable(entity_type: "node", bundle: "article")
2. mcp_layout_allow_custom(entity_type: "node", bundle: "article", allow: true)
3. mcp_layout_add_section(entity_type: "node", bundle: "article",
     layout_id: "layout_twocol", delta: 0)
```

### Recipe Application

```
User: "Apply the blog recipe to quickly set up a blog"

AI calls:
1. mcp_recipes_validate(recipe: "core/recipes/blog")
2. mcp_recipes_apply(recipe: "core/recipes/blog")
```

---

## Success Metrics

| Metric | Target (Year 1) | Status |
|--------|-----------------|--------|
| Drupal.org installs | 500+ | Tracking |
| Tools available | 90+ | ✅ 204 tools |
| Write operations | Full site scaffolding | ✅ Complete |
| Integrations | 29 submodules covering core, contrib, analysis, and utilities | ✅ Complete |
| Admin UI | Settings form, status page | ✅ Complete |
| Production hardening | Rate limiting, webhooks, read-only mode | ✅ Complete |
| Test coverage | Unit + Kernel tests for all services | ✅ Complete |
| Analysis tools | SEO, accessibility, security, performance | ✅ Complete |
| Community contributions | 5+ contributors | Tracking |

---

## References

- [MCP Specification](https://modelcontextprotocol.io/)
- [Sentry MCP](https://docs.sentry.io/product/sentry-mcp/) - Inspiration
- [mcp_server](https://www.drupal.org/project/mcp_server) - Transport layer
- [Tool API](https://www.drupal.org/project/tool) - Plugin framework
- [Drupal Entity API](https://www.drupal.org/docs/drupal-apis/entity-api)
- [Drupal Field API](https://www.drupal.org/docs/drupal-apis/entity-api/fieldable-entities)
