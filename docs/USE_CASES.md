# MCP Tools Use Cases

This document collects short, real-world workflows you can run with MCP Tools. Each includes scope
and module prerequisites for quick setup.

## 1) Site Health + Risk Snapshot (10 minutes)

**Goal:** Quickly understand whether a site is safe and healthy.

**Modules:** `mcp_tools`

**Scopes:** `read`

**Suggested prompts**
- "Give me a site health summary and flag risky areas."
- "Are we behind on security updates or cron?"

**Recommended tools**
1. `mcp_tools_get_site_status`
2. `mcp_tools_check_security_updates`
3. `mcp_tools_analyze_watchdog`
4. `mcp_tools_get_config_status`

**Outcome**
- Consolidated view of versions, cron status, security advisories, and recent errors.

## 2) Blog Build (15 minutes)

**Goal:** Spin up a blog structure with content types, taxonomies, and views.

**Modules:** `mcp_tools_templates`, `mcp_tools_content` (optional)

**Scopes:** `write`

**Suggested prompts**
- "Create a blog structure with categories and tags."
- "Set up a blog landing page and a few starter posts."

**Recommended tools**
1. `mcp_templates_preview` (template: `blog`)
2. `mcp_templates_apply`
3. `mcp_create_content` (optional starter content)

## 3) CSV Migration (30 minutes)

**Goal:** Import content from a CSV file with validation and field mapping.

**Modules:** `mcp_tools_migration`

**Scopes:** `write`

**Suggested prompts**
- "Validate my CSV and import it as Article content."

**Recommended tools**
1. `mcp_migration_validate`
2. `mcp_migration_field_mapping`
3. `mcp_migration_import_csv`
4. `mcp_migration_status`

## 4) Content Audit (20 minutes)

**Goal:** Find stale content, duplicates, and SEO issues.

**Modules:** `mcp_tools_analysis`

**Scopes:** `read`

**Suggested prompts**
- "Audit content quality and tell me what needs attention."

**Recommended tools**
1. `mcp_analysis_content_audit`
2. `mcp_analysis_duplicates`
3. `mcp_analysis_seo`

## 5) Operational Cleanup (10 minutes)

**Goal:** Clear targeted caches and reindex search.

**Modules:** `mcp_tools_cache`, `mcp_tools_search_api` (optional)

**Scopes:** `write`

**Suggested prompts**
- "Clear render cache for a node and reindex search."

**Recommended tools**
1. `mcp_cache_clear_entity`
2. `mcp_search_api_reindex`

---

Want to add more? Open a PR and include a short workflow and the tools used.
