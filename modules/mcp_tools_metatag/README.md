# MCP Tools - Metatag

SEO metatag management for MCP Tools. Integrates with the Drupal Metatag contrib module to get and set metatags on entities.

## Tools (5)

| Tool | Description |
|------|-------------|
| `mcp_metatag_get_defaults` | Get default metatag configuration |
| `mcp_metatag_get_entity` | Get metatags for a specific entity |
| `mcp_metatag_set_entity` | Set metatags on an entity (write) |
| `mcp_metatag_list_groups` | List available metatag groups |
| `mcp_metatag_list_tags` | List all available metatag tags |

## Requirements

- mcp_tools (base module)
- metatag:metatag (contrib module)

## Installation

```bash
drush en mcp_tools_metatag
```

## Example Usage

### View Current Metatags for a Node

```
User: "Show me the SEO metatags for node 42"

AI calls: mcp_metatag_get_entity(
  entity_type: "node",
  entity_id: 42
)
```

### Set Basic SEO Metatags

```
User: "Update the SEO for the About Us page (node 15)"

AI calls: mcp_metatag_set_entity(
  entity_type: "node",
  entity_id: 15,
  tags: {
    "title": "About Us | Company Name",
    "description": "Learn about our company history, mission, and the team behind our success.",
    "robots": "index, follow"
  }
)
```

### Set Open Graph Tags for Social Sharing

```
User: "Add social media sharing tags to our blog post"

AI calls: mcp_metatag_set_entity(
  entity_type: "node",
  entity_id: 123,
  tags: {
    "og_title": "10 Tips for Better SEO",
    "og_description": "Discover proven strategies to improve your search rankings.",
    "og_type": "article",
    "og_image": "https://example.com/images/seo-tips.jpg"
  }
)
```

### Set Twitter Card Tags

```
User: "Configure Twitter cards for this article"

AI calls: mcp_metatag_set_entity(
  entity_type: "node",
  entity_id: 123,
  tags: {
    "twitter_cards_type": "summary_large_image",
    "twitter_cards_title": "10 Tips for Better SEO",
    "twitter_cards_description": "Discover proven strategies to improve your search rankings.",
    "twitter_cards_image": "https://example.com/images/seo-tips-twitter.jpg"
  }
)
```

### Check Default Configurations

```
User: "What are the default metatags for articles?"

AI calls: mcp_metatag_get_defaults(
  type: "node__article"
)
```

### List Available Tags

```
User: "What metatag options are available?"

AI calls: mcp_metatag_list_tags()
```

## Common SEO Patterns

### Homepage SEO

```json
{
  "title": "Company Name | Your Tagline",
  "description": "Brief 150-160 character description of your site.",
  "robots": "index, follow",
  "og_title": "Company Name",
  "og_description": "Your company description for social sharing.",
  "og_type": "website",
  "og_image": "https://example.com/og-image.jpg",
  "twitter_cards_type": "summary_large_image"
}
```

### Blog Article SEO

```json
{
  "title": "[node:title] | Blog | Company Name",
  "description": "[node:summary]",
  "robots": "index, follow",
  "og_type": "article",
  "og_title": "[node:title]",
  "og_description": "[node:summary]",
  "article_published_time": "[node:created:custom:c]",
  "article_author": "[node:author:display-name]"
}
```

### Product Page SEO

```json
{
  "title": "[node:title] | Buy Online | Company Name",
  "description": "[node:field_product_description]",
  "robots": "index, follow",
  "og_type": "product",
  "og_title": "[node:title]",
  "product_price_amount": "[node:field_price]",
  "product_price_currency": "USD"
}
```

### NoIndex Pages (Login, Admin, etc.)

```json
{
  "robots": "noindex, nofollow"
}
```

### Canonical URLs

```json
{
  "canonical_url": "[node:url:absolute]"
}
```

## Available Metatag Groups

The Metatag module organizes tags into groups:

- **Basic** - Core tags (title, description, keywords, robots)
- **Open Graph** - Facebook/LinkedIn sharing (og_title, og_description, og_image, etc.)
- **Twitter Cards** - Twitter sharing (twitter_cards_type, twitter_cards_title, etc.)
- **Dublin Core** - Academic/library metadata
- **Advanced** - Technical tags (canonical, shortlink, etc.)
- **Google+** - Google Plus tags (deprecated but may exist)
- **App Links** - Mobile app deep linking
- **Site Verification** - Google/Bing verification codes

Additional groups may be available with Metatag submodules:
- metatag_open_graph
- metatag_twitter_cards
- metatag_dublin_core
- metatag_google_plus
- metatag_app_links
- metatag_mobile
- metatag_hreflang
- metatag_facebook

## Token Support

Metatag values support Drupal tokens:

- `[node:title]` - Node title
- `[node:summary]` - Node summary/teaser
- `[node:url:absolute]` - Full URL
- `[node:author:display-name]` - Author name
- `[node:created:custom:Y-m-d]` - Creation date
- `[site:name]` - Site name
- `[current-page:url]` - Current URL

## Safety Features

- **Write protection:** Setting metatags requires write access via AccessManager
- **Tag validation:** Invalid tag names are rejected with helpful error messages
- **Audit logging:** All metatag changes are logged for tracking
- **Revision support:** Changes create new entity revisions when supported
- **Non-destructive:** Existing tags are preserved unless explicitly overwritten
