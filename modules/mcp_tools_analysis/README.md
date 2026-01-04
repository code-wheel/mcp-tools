# MCP Tools - Analysis

Site health and content analysis tools for MCP Tools. All tools are read-only and provide diagnostic information with actionable suggestions.

## Tools (8)

| Tool | Description |
|------|-------------|
| `mcp_analysis_broken_links` | Scan content for broken internal links (404s) |
| `mcp_analysis_content_audit` | Find stale, orphaned, and draft content |
| `mcp_analysis_seo` | Analyze SEO for a specific entity (meta tags, headings, alt text) |
| `mcp_analysis_security` | Review permissions and identify security issues |
| `mcp_analysis_unused_fields` | Find fields with no data across entities |
| `mcp_analysis_performance` | Analyze cache settings, errors, and database performance |
| `mcp_analysis_accessibility` | Check accessibility (WCAG) for a specific entity |
| `mcp_analysis_duplicates` | Find similar/duplicate content based on field values |

## Requirements

- mcp_tools (base module)

## Installation

```bash
drush en mcp_tools_analysis
```

## Example Usage

### Find Broken Links

```
User: "Check the site for broken internal links"

AI calls: mcp_analysis_broken_links(
  limit: 100
  # Optional for STDIO/CLI use:
  # base_url: "https://example.com"
)

Response includes:
- List of broken links with source content info
- Suggestions for fixing issues
```

### Content Audit

```
User: "Find content that hasn't been updated in over a year"

AI calls: mcp_analysis_content_audit(
  stale_days: 365,
  include_drafts: true
)

Response includes:
- Stale content list with last update dates
- Orphaned (unpublished) content
- Draft content awaiting publication
```

### SEO Analysis

```
User: "Analyze SEO for article node 42"

AI calls: mcp_analysis_seo(
  entity_type: "node",
  entity_id: 42
)

Response includes:
- SEO score (0-100)
- Issues with title, meta description, headings
- Missing alt text warnings
- Content length analysis
```

### Security Audit

```
User: "Run a security audit on the site"

AI calls: mcp_analysis_security()

Response includes:
- Critical issues (dangerous anonymous permissions)
- Warnings (overly permissive roles)
- Admin user count
- Registration mode settings
```

### Find Unused Fields

```
User: "What fields are not being used?"

AI calls: mcp_analysis_unused_fields()

Response includes:
- List of fields with no data
- Entity type and bundle info
- Suggestions for cleanup
```

### Performance Analysis

```
User: "Check site performance"

AI calls: mcp_analysis_performance()

Response includes:
- Cache configuration status
- Recent PHP/system errors from watchdog
- Largest database tables
- Performance optimization suggestions
```

### Accessibility Check

```
User: "Check accessibility for the homepage node"

AI calls: mcp_analysis_accessibility(
  entity_type: "node",
  entity_id: 1
)

Response includes:
- Accessibility issues with WCAG references
- Missing alt text
- Heading hierarchy problems
- Generic link text warnings
```

### Find Duplicate Content

```
User: "Find articles with similar titles"

AI calls: mcp_analysis_duplicates(
  content_type: "article",
  field: "title",
  threshold: 0.8
)

Response includes:
- Pairs of similar content with similarity percentage
- Creation dates to identify original vs duplicate
- Suggestions for handling duplicates
```

## Analysis Categories

### Content Health
- **Stale Content**: Content not updated within specified days
- **Orphaned Content**: Unpublished content with no recent activity
- **Drafts**: Content stuck in draft state

### SEO Checks
- Title length (30-60 characters recommended)
- Meta description presence and length
- Heading structure (no H1 in body, proper hierarchy)
- Image alt text
- Content length (300+ words recommended)

### Security Checks
- Anonymous user permissions
- Authenticated user permissions
- Overly permissive custom roles
- User registration settings
- Admin account count
- PHP Filter module detection

### Accessibility Checks (WCAG)
- 1.1.1: Images without alt text
- 1.3.1: Heading hierarchy, tables without headers
- 1.4.1: Color-only information
- 2.4.4: Empty or generic link text

### Performance Metrics
- Page cache max age
- CSS/JS aggregation status
- Watchdog error log analysis
- Database table sizes

## Output Format

All tools return a consistent structure:

```json
{
  "success": true,
  "data": {
    // Tool-specific results
    "suggestions": [
      "Actionable recommendation 1",
      "Actionable recommendation 2"
    ]
  }
}
```

## Notes

- All tools are read-only and do not modify any data
- Large site scans may take time - use limit parameters where available
- Suggestions are informational and should be reviewed before action
- For comprehensive audits, combine multiple tools
