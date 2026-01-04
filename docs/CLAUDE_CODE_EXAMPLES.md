# Claude Code Configuration Examples

This guide shows how to configure Claude Code to work with MCP Tools for AI-powered Drupal site building.

## Quick Start

### 1. Configure MCP Server Connection

Add to your Claude Code configuration (`.claude/settings.json` or global settings):

```json
{
  "mcpServers": {
    "drupal-local": {
      "command": "drush",
      "args": ["mcp-tools:serve", "--quiet", "--scope=read,write"],
      "cwd": "/path/to/your/drupal/site",
      "env": {}
    }
  }
}
```

### 2. For Read-Only Access (Production)

```json
{
  "mcpServers": {
    "drupal-prod": {
      "command": "drush",
      "args": ["mcp-tools:serve", "--quiet", "--scope=read"],
      "cwd": "/path/to/drupal",
      "env": {}
    }
  }
}
```

### 3. HTTP Transport (Remote Server)

This requires enabling `mcp_tools_remote` and configuring the endpoint at `/admin/config/services/mcp-tools/remote`.

```json
{
  "mcpServers": {
    "drupal-remote": {
      "url": "https://your-site.com/_mcp_tools",
      "headers": {
        "Authorization": "Bearer your-api-key"
      }
    }
  }
}
```

## Example Workflows

### Create a Blog

```
User: Create a blog with articles, categories, tags, and an author role

AI uses:
1. mcp_structure_create_content_type(id: "article", label: "Article")
2. mcp_structure_add_field(bundle: "article", name: "body", type: "text_long")
3. mcp_structure_add_field(bundle: "article", name: "image", type: "image")
4. mcp_structure_create_vocabulary(id: "categories", label: "Categories")
5. mcp_structure_create_vocabulary(id: "tags", label: "Tags")
6. mcp_structure_add_field(bundle: "article", name: "category", type: "entity_reference", settings: {target_type: "taxonomy_term", target_bundles: ["categories"]})
7. mcp_structure_add_field(bundle: "article", name: "tags", type: "entity_reference", settings: {target_type: "taxonomy_term", target_bundles: ["tags"]}, cardinality: -1)
8. mcp_structure_create_role(id: "author", label: "Author")
9. mcp_structure_grant_permissions(role: "author", permissions: ["create article content", "edit own article content"])
10. mcp_views_create_content_list(content_type: "article", label: "Recent Articles")
```

### Or Use a Template

```
User: Set up a blog on my site

AI uses:
1. mcp_templates_preview(id: "blog")  # Check what will be created
2. mcp_templates_apply(id: "blog")    # Apply the template
```

### Create Content in Bulk

```
User: Create 10 sample articles for testing

AI uses:
mcp_batch_create_content(
  content_type: "article",
  items: [
    {title: "Getting Started with Drupal", body: "..."},
    {title: "Understanding Content Types", body: "..."},
    {title: "Working with Views", body: "..."},
    // ... more items
  ]
)
```

### Import Content from CSV

```
User: Import these articles from a CSV file

AI uses:
1. mcp_migration_field_mapping(content_type: "article")  # Get required fields
2. mcp_migration_validate(content_type: "article", items: [...])  # Validate first
3. mcp_migration_import_csv(
     content_type: "article",
     csv_data: "title,body,category\nFirst Post,Content here,news\n...",
     field_mapping: {title: "title", body: "body", field_category: "category"}
   )
```

### SEO and Content Audit

```
User: Check the SEO of my homepage

AI uses:
1. mcp_analysis_seo(entity_type: "node", entity_id: 1)

User: Find any content issues on my site

AI uses:
1. mcp_analysis_content_audit(options: {stale_days: 90, include_drafts: true})
2. mcp_analysis_broken_links(limit: 50)
3. mcp_analysis_duplicates(content_type: "article", field: "title", threshold: 0.8)
```

### URL Management

```
User: Set up URL patterns for my content

AI uses:
1. mcp_pathauto_create(
     id: "article_pattern",
     label: "Article Pattern",
     pattern: "/blog/[node:created:custom:Y]/[node:title]",
     entity_type: "node",
     bundle: "article"
   )
2. mcp_pathauto_generate(entity_type: "node", bundle: "article", update: true)

User: Add a redirect from the old blog URL

AI uses:
mcp_redirect_create(source: "old-blog", destination: "/blog", status_code: 301)
```

### Search Configuration

```
User: Check the search index status and reindex if needed

AI uses:
1. mcp_search_api_status(index_id: "default_index")
2. mcp_search_api_reindex(index_id: "default_index")
3. mcp_search_api_index(index_id: "default_index", limit: 100)
```

### Theme and Layout

```
User: Switch to Olivero theme and set up a two-column layout for articles

AI uses:
1. mcp_theme_enable(theme: "olivero")
2. mcp_theme_set_default(theme: "olivero")
3. mcp_layout_enable(entity_type: "node", bundle: "article")
4. mcp_layout_allow_custom(entity_type: "node", bundle: "article", allow: true)
5. mcp_layout_add_section(entity_type: "node", bundle: "article", layout_id: "layout_twocol", delta: 0)
```

### Cache and Performance

```
User: Clear the cache and check performance

AI uses:
1. mcp_cache_clear_all()
2. mcp_analysis_performance()
3. mcp_cache_get_status()
```

### Scheduled Publishing

```
User: Schedule this article to publish next Monday at 9am

AI uses:
mcp_scheduler_publish(
  entity_type: "node",
  entity_id: 123,
  publish_on: "2024-01-15 09:00:00"
)
```

## Best Practices

### 1. Always Preview Before Applying Templates

```
# First preview
mcp_templates_preview(id: "blog")

# Then apply if everything looks good
mcp_templates_apply(id: "blog")
```

### 2. Validate Before Importing

```
# First validate
mcp_migration_validate(content_type: "article", items: [...])

# Then import if validation passes
mcp_migration_import_json(content_type: "article", items: [...])
```

### 3. Export Configuration After Changes

After using MCP Tools to create structures:

```
# Check what MCP created
mcp_config_mcp_changes()

# Export to sync directory
mcp_config_export()

# Then commit to Git:
# git add config/
# git commit -m "Export MCP-created configuration"
```

### 4. Use Read-Only Mode in Production

Always configure production connections with read-only scope:

```json
{
  "env": {
    "MCP_SCOPE": "read"
  }
}
```

### 5. Run Analysis Before Major Changes

```
# Before a major site update
mcp_analysis_security()
mcp_analysis_content_audit()
mcp_analysis_broken_links()
```

## Troubleshooting

### "Permission denied" errors

MCP Tools access is enforced at multiple layers:

- **MCP scope** (read/write/admin)
- **Drupal permissions** (`mcp_tools use {category}` for the tool category)
- **Global site mode** (read-only or config-only)

### Rate limiting

If you hit rate limits, wait and try again. Configure limits in Drupal at `/admin/config/services/mcp-tools`.

### Connection issues

Test the connection with a simple read operation:
```
mcp_tools_get_site_status()
```

## Security Considerations

1. **Never use write scope in production** without careful consideration
2. **Export configuration to Git** after making structural changes
3. **Review audit logs** regularly at `/admin/reports/dblog`
4. **Use IP allowlisting** for HTTP transport endpoints
5. **Rotate API keys** periodically if using authentication
