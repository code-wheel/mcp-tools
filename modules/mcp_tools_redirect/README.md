# MCP Tools - Redirect

URL redirect management for MCP Tools. Create, update, and manage URL redirects for SEO and link maintenance.

## Tools (7)

| Tool | Description |
|------|-------------|
| `mcp_redirect_list` | List all redirects with pagination |
| `mcp_redirect_get` | Get redirect details by ID |
| `mcp_redirect_create` | Create a new redirect |
| `mcp_redirect_update` | Update an existing redirect |
| `mcp_redirect_delete` | Delete a redirect |
| `mcp_redirect_find` | Find a redirect by source path |
| `mcp_redirect_import` | Bulk import multiple redirects |

## Requirements

- mcp_tools (base module)
- redirect:redirect

## Installation

```bash
drush en mcp_tools_redirect
```

## Example Usage

### Create a Simple Redirect

```
User: "Create a redirect from /old-page to /new-page"

AI calls: mcp_redirect_create(
  source: "old-page",
  destination: "/new-page",
  status_code: 301
)
```

### Create a Temporary Redirect

```
User: "Create a temporary redirect from /maintenance to /under-construction"

AI calls: mcp_redirect_create(
  source: "maintenance",
  destination: "/under-construction",
  status_code: 302
)
```

### Redirect to External URL

```
User: "Redirect /partner to our partner site"

AI calls: mcp_redirect_create(
  source: "partner",
  destination: "https://partner-site.com",
  status_code: 301
)
```

### Find Existing Redirect

```
User: "Check if there's already a redirect for /old-blog"

AI calls: mcp_redirect_find(
  source: "old-blog"
)
```

### Update a Redirect

```
User: "Change redirect 123 to point to /updated-page instead"

AI calls: mcp_redirect_update(
  id: 123,
  destination: "/updated-page"
)
```

### Bulk Import Redirects

```
User: "Import these redirects from the migration"

AI calls: mcp_redirect_import(
  redirects: [
    { "source": "page-1", "destination": "/new/page-1" },
    { "source": "page-2", "destination": "/new/page-2" },
    { "source": "page-3", "destination": "/new/page-3", "status_code": 302 }
  ]
)
```

### List All Redirects

```
User: "Show me all redirects"

AI calls: mcp_redirect_list(
  limit: 50,
  offset: 0
)
```

### Language-Specific Redirect

```
User: "Create a redirect for the German version of the old about page"

AI calls: mcp_redirect_create(
  source: "ueber-uns-alt",
  destination: "/de/ueber-uns",
  status_code: 301,
  language: "de"
)
```

## Redirect Status Codes

| Code | Type | Description |
|------|------|-------------|
| 301 | Permanent | Resource has permanently moved. Search engines transfer SEO value. |
| 302 | Temporary | Resource temporarily moved. Search engines keep original URL indexed. |
| 303 | See Other | Response to POST request should be retrieved via GET. |
| 307 | Temporary Redirect | Like 302, but preserves the request method. |

## Redirect Data Structure

Each redirect contains:

- `id` - Unique redirect ID
- `source` - Source path (without leading slash)
- `source_url` - Full source URL
- `destination` - Destination path or URL
- `status_code` - HTTP redirect status code
- `language` - Language code (or "und" for language-neutral)
- `count` - Number of times the redirect has been used
- `created` - Creation timestamp

## Safety Features

- **Duplicate prevention:** Cannot create redirects for existing source paths
- **Validation:** Source and destination paths validated before save
- **Access control:** Write operations require appropriate permissions
- **Audit logging:** All redirect operations are logged

## Common Use Cases

1. **Site migrations:** Redirect old URLs to new structure
2. **SEO cleanup:** Fix broken links and consolidate duplicate content
3. **Marketing campaigns:** Create vanity URLs that redirect to campaign pages
4. **Content reorganization:** Maintain links when moving content
5. **Domain changes:** Redirect from old domain patterns to new ones
