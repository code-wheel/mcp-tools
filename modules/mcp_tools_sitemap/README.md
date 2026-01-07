# MCP Tools - Sitemap

Simple XML Sitemap integration for MCP Tools. Manage sitemap variants, settings, entity inclusion configuration, and regeneration.

## Tools (7)

| Tool | Description |
|------|-------------|
| `mcp_sitemap_status` | Get sitemap generation status |
| `mcp_sitemap_list` | List all sitemap variants |
| `mcp_sitemap_get_settings` | Get settings for a sitemap variant |
| `mcp_sitemap_update_settings` | Update sitemap settings (write) |
| `mcp_sitemap_regenerate` | Regenerate sitemap(s) (write) |
| `mcp_sitemap_entity_settings` | Get entity inclusion settings |
| `mcp_sitemap_set_entity` | Set entity inclusion settings (write) |

## Requirements

- mcp_tools (base module)
- simple_sitemap:simple_sitemap (contrib module)

## Installation

```bash
drush en mcp_tools_sitemap
```

## Example Usage

### Check Sitemap Status

```
User: "What's the current sitemap status?"

AI calls: mcp_sitemap_status()

Response:
{
  "sitemaps": {
    "default": {
      "id": "default",
      "label": "Default",
      "status": "generated",
      "link_count": 150,
      "chunk_count": 1,
      "is_enabled": true
    }
  },
  "generator_queue": {
    "total": 0,
    "processed": 0,
    "remaining": 0
  }
}
```

### List All Sitemap Variants

```
User: "Show me all configured sitemaps"

AI calls: mcp_sitemap_list()
```

### Get Sitemap Settings

```
User: "What are the current sitemap settings?"

AI calls: mcp_sitemap_get_settings(
  variant: "default"
)
```

### Update Sitemap Settings

```
User: "Enable cron generation and set max links to 2000"

AI calls: mcp_sitemap_update_settings(
  variant: "default",
  settings: {
    "global": {
      "cron_generate": true,
      "max_links": 2000
    }
  }
)
```

### Regenerate Sitemap

```
User: "Regenerate the sitemap"

AI calls: mcp_sitemap_regenerate()

# Or for a specific variant:
AI calls: mcp_sitemap_regenerate(
  variant: "default"
)
```

### Check Entity Inclusion Settings

```
User: "Are articles included in the sitemap?"

AI calls: mcp_sitemap_entity_settings(
  entity_type: "node",
  bundle: "article"
)
```

### Configure Entity Inclusion

```
User: "Include blog posts in sitemap with high priority"

AI calls: mcp_sitemap_set_entity(
  entity_type: "node",
  bundle: "blog",
  settings: {
    "index": true,
    "priority": "0.8",
    "changefreq": "weekly",
    "include_images": true
  }
)
```

### Exclude Content Type from Sitemap

```
User: "Remove landing pages from the sitemap"

AI calls: mcp_sitemap_set_entity(
  entity_type: "node",
  bundle: "landing_page",
  settings: {
    "index": false
  }
)
```

### Configure Taxonomy Terms

```
User: "Add category terms to sitemap with monthly updates"

AI calls: mcp_sitemap_set_entity(
  entity_type: "taxonomy_term",
  bundle: "categories",
  settings: {
    "index": true,
    "priority": "0.6",
    "changefreq": "monthly"
  }
)
```

## Settings Reference

### Global Settings

| Setting | Type | Description |
|---------|------|-------------|
| `max_links` | integer | Maximum links per sitemap chunk |
| `cron_generate` | boolean | Enable automatic generation on cron |
| `cron_generate_interval` | integer | Seconds between cron generations |
| `remove_duplicates` | boolean | Remove duplicate URLs |
| `skip_untranslated` | boolean | Skip untranslated content |
| `base_url` | string | Override base URL for sitemap |
| `xsl` | boolean | Enable XSL stylesheet |

### Entity Settings

| Setting | Type | Values | Description |
|---------|------|--------|-------------|
| `index` | boolean | true/false | Include in sitemap |
| `priority` | string | 0.0-1.0 | URL priority hint |
| `changefreq` | string | always, hourly, daily, weekly, monthly, yearly, never | Change frequency hint |
| `include_images` | boolean | true/false | Include images in sitemap |

### Priority Guidelines

| Priority | Use Case |
|----------|----------|
| 1.0 | Homepage |
| 0.8-0.9 | Important landing pages |
| 0.6-0.7 | Blog posts, articles |
| 0.5 | Standard content (default) |
| 0.3-0.4 | Archive pages, less important |
| 0.1-0.2 | Legal pages, rarely updated |

### Change Frequency Guidelines

| Frequency | Use Case |
|-----------|----------|
| always | Real-time content |
| hourly | News sites, active forums |
| daily | Blogs, news |
| weekly | Most content sites |
| monthly | Documentation, reference |
| yearly | Archive content |
| never | Historical records |

## Common Workflows

### Initial Sitemap Setup

1. Check available entity types:
```
mcp_sitemap_get_settings(variant: "default")
```

2. Enable desired content types:
```
mcp_sitemap_set_entity(entity_type: "node", bundle: "page", settings: {"index": true, "priority": "0.7"})
mcp_sitemap_set_entity(entity_type: "node", bundle: "article", settings: {"index": true, "priority": "0.8", "changefreq": "weekly"})
```

3. Regenerate sitemap:
```
mcp_sitemap_regenerate()
```

### SEO Audit

1. Check current status:
```
mcp_sitemap_status()
```

2. Review entity settings:
```
mcp_sitemap_entity_settings(entity_type: "node")
```

3. Verify specific content types are included:
```
mcp_sitemap_entity_settings(entity_type: "node", bundle: "article")
```

## Safety Features

- **Write protection:** Modifying settings and regenerating requires write access via AccessManager
- **Audit logging:** All changes are logged for tracking
- **Validation:** Priority and changefreq values are validated before saving
- **Non-destructive:** Reading settings never modifies configuration
