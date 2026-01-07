# MCP Tools - Pathauto

URL alias pattern management for MCP Tools. Integrates with the Drupal Pathauto contrib module to create, update, delete patterns and bulk generate URL aliases.

## Tools (6)

| Tool | Description | Write Operation |
|------|-------------|-----------------|
| `mcp_pathauto_list_patterns` | List all URL alias patterns | No |
| `mcp_pathauto_get_pattern` | Get details of a specific pattern | No |
| `mcp_pathauto_create` | Create a new URL alias pattern | Yes |
| `mcp_pathauto_update` | Update an existing pattern | Yes |
| `mcp_pathauto_delete` | Delete a pattern | Yes |
| `mcp_pathauto_generate` | Bulk generate URL aliases | Yes |

## Requirements

- mcp_tools (base module)
- pathauto:pathauto (contrib module) - https://www.drupal.org/project/pathauto

## Installation

```bash
composer require drupal/pathauto
drush en pathauto mcp_tools_pathauto
```

## Example Usage

### List All Patterns

```
User: "Show me all URL alias patterns"

AI calls: mcp_pathauto_list_patterns()
```

### List Patterns for Specific Entity Type

```
User: "What URL patterns are configured for nodes?"

AI calls: mcp_pathauto_list_patterns(
  entity_type: "node"
)
```

### Get Pattern Details

```
User: "Show me the details of the article_pattern"

AI calls: mcp_pathauto_get_pattern(
  id: "article_pattern"
)
```

### Create Pattern for Articles

```
User: "Create a URL pattern for blog articles that uses /blog/[title]"

AI calls: mcp_pathauto_create(
  id: "blog_article",
  label: "Blog Article Pattern",
  pattern: "blog/[node:title]",
  entity_type: "node",
  bundle: "article"
)
```

### Create Pattern for Taxonomy Terms

```
User: "Set up URL aliases for product categories"

AI calls: mcp_pathauto_create(
  id: "product_category",
  label: "Product Category Pattern",
  pattern: "products/category/[term:name]",
  entity_type: "taxonomy_term",
  bundle: "product_categories"
)
```

### Create Pattern for Users

```
User: "Create user profile URL pattern"

AI calls: mcp_pathauto_create(
  id: "user_profile",
  label: "User Profile Pattern",
  pattern: "members/[user:name]",
  entity_type: "user"
)
```

### Update Pattern

```
User: "Change the blog pattern to use /articles/ instead of /blog/"

AI calls: mcp_pathauto_update(
  id: "blog_article",
  pattern: "articles/[node:title]"
)
```

### Disable a Pattern

```
User: "Disable the old news pattern"

AI calls: mcp_pathauto_update(
  id: "news_pattern",
  status: false
)
```

### Delete Pattern

```
User: "Remove the deprecated event pattern"

AI calls: mcp_pathauto_delete(
  id: "old_event_pattern"
)
```

### Generate Aliases for All Nodes

```
User: "Generate URL aliases for all nodes"

AI calls: mcp_pathauto_generate(
  entity_type: "node"
)
```

### Generate Aliases for Specific Content Type

```
User: "Create URL aliases for all articles that are missing them"

AI calls: mcp_pathauto_generate(
  entity_type: "node",
  bundle: "article"
)
```

### Regenerate All Aliases (Update Existing)

```
User: "Regenerate all product page URLs"

AI calls: mcp_pathauto_generate(
  entity_type: "node",
  bundle: "product",
  update: true
)
```

## Common Token Patterns

### Node Patterns

```
# Simple title-based
[node:title]

# With content type prefix
[node:content-type]/[node:title]

# Date-based blog pattern
blog/[node:created:custom:Y]/[node:created:custom:m]/[node:title]

# Category + title
[node:field_category:entity:name]/[node:title]

# Author + title
[node:author:name]/[node:title]
```

### Taxonomy Term Patterns

```
# Simple term name
[term:name]

# Vocabulary + term
[term:vocabulary]/[term:name]

# Hierarchical (parent/child)
[term:parent:name]/[term:name]
```

### User Patterns

```
# Username
users/[user:name]

# Role-based (requires token module)
[user:role]/[user:name]
```

### Media Patterns

```
# Media type + name
media/[media:bundle]/[media:name]

# Simple name
files/[media:name]
```

## Pattern Priority

When multiple patterns could match an entity, Pathauto uses the following priority:

1. **Weight**: Lower weight patterns are checked first
2. **Specificity**: Bundle-specific patterns take precedence over generic patterns
3. **First match**: The first matching pattern is used

Example:
```
Weight 0: "Blog Article" - blog/[node:title] (bundle: article)
Weight 1: "Default Node" - content/[node:title] (no bundle restriction)
```

An article node will get `blog/my-article-title`, while a page node will get `content/my-page-title`.

## Bulk Generation Notes

- The generate tool processes up to 500 entities per call to prevent timeouts
- For large sites, run the generate tool multiple times
- Use `update: false` (default) to only create missing aliases
- Use `update: true` to regenerate all aliases (useful after pattern changes)

## Safety Features

- **Write protection:** Pattern creation, updates, deletes, and bulk generation require write access via AccessManager
- **Audit logging:** All write operations are logged for tracking
- **Validation:** Entity types and bundle names are validated before pattern creation
- **Non-destructive defaults:** Bulk generation only creates missing aliases by default

## Configuration

Pathauto settings can be configured at:
- `/admin/config/search/path/patterns` - Manage patterns
- `/admin/config/search/path/settings` - General settings

### Recommended Settings

1. **Transliterate prior to creating alias**: Enable for clean URLs
2. **Reduce strings to letters and numbers**: Enable to remove special characters
3. **Update action**: Choose how to handle existing aliases when content is updated
