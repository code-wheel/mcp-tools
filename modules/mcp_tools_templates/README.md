# MCP Tools - Templates

Site configuration templates for MCP Tools. Apply pre-built templates for common site types like blogs, portfolios, business sites, and documentation.

## Tools (5)

| Tool | Description |
|------|-------------|
| `mcp_templates_list` | List available templates |
| `mcp_templates_get` | Get template details |
| `mcp_templates_preview` | Preview what would be created (dry-run) |
| `mcp_templates_apply` | Apply a template to the site |
| `mcp_templates_export` | Export current config as a template |

## Requirements

- mcp_tools (base module)
- Drupal 10.3+
- `admin` scope required for apply and export operations

## Installation

```bash
drush en mcp_tools_templates
```

## Built-in Templates

### Blog
A complete blog setup with articles, categories, tags, and author workflow.

**Components:**
- **Content Types:** Article (body, featured image, tags, categories)
- **Vocabularies:** Tags, Categories (hierarchical)
- **Roles:** Author (create/edit/delete own articles)
- **Views:** Recent Articles (page at /articles, block)

### Portfolio
Showcase projects with skills taxonomy and gallery support.

**Components:**
- **Content Types:** Project (description, images, skills, URL, client, completion date)
- **Vocabularies:** Skills
- **Media Types:** Gallery
- **Views:** Portfolio Grid (page at /portfolio)

### Business
Business site with pages, services, team members, and contact form.

**Components:**
- **Content Types:** Page, Service, Team Member
- **Webforms:** Contact form
- **Views:** Services List, Our Team

### Documentation
Technical documentation with hierarchical structure and API references.

**Components:**
- **Content Types:** Documentation (content, TOC section, version, code examples), API Reference (endpoint, method, parameters, response)
- **Vocabularies:** Table of Contents (hierarchical)
- **Views:** Documentation TOC, API Reference List

## Example Usage

### List Available Templates

```
User: "What site templates are available?"

AI calls: mcp_templates_list()

Returns: [
  {id: "blog", label: "Blog", description: "A complete blog setup...", category: "Content"},
  {id: "portfolio", label: "Portfolio", description: "Showcase projects...", category: "Content"},
  {id: "business", label: "Business", description: "Business site...", category: "Corporate"},
  {id: "documentation", label: "Documentation", description: "Technical documentation...", category: "Technical"}
]
```

### Get Template Details

```
User: "What does the blog template include?"

AI calls: mcp_templates_get(template_id: "blog")

Returns: {
  id: "blog",
  label: "Blog",
  components: {
    content_types: {article: {...}},
    vocabularies: {tags: {...}, categories: {...}},
    roles: {author: {...}},
    views: {recent_articles: {...}}
  }
}
```

### Preview Template (Dry-Run)

```
User: "What would happen if I apply the blog template?"

AI calls: mcp_templates_preview(template_id: "blog")

Returns: {
  template_id: "blog",
  will_create: [
    {type: "content_type", id: "article", label: "Article"},
    {type: "vocabulary", id: "tags", label: "Tags"},
    ...
  ],
  will_skip: [
    {type: "vocabulary", id: "categories", reason: "Already exists"}
  ],
  conflicts: []
}
```

### Apply a Template

```
User: "Set up a blog on this site"

AI calls:
1. mcp_templates_preview(template_id: "blog")
2. mcp_templates_apply(template_id: "blog")

Returns: {
  template: "blog",
  created: [
    {type: "vocabulary", id: "tags", label: "Tags"},
    {type: "vocabulary", id: "categories", label: "Categories"},
    {type: "content_type", id: "article", label: "Article"},
    {type: "role", id: "author", label: "Author"},
    {type: "view", id: "recent_articles", label: "Recent Articles"}
  ],
  skipped: [],
  message: "Template \"Blog\" applied successfully. Created 5 components."
}
```

### Apply Specific Components Only

```
User: "Just add the blog content type and tags, not the views"

AI calls: mcp_templates_apply(
  template_id: "blog",
  components: ["content_types", "vocabularies"]
)
```

### Export Current Config as Template

```
User: "Export my current article and page content types as a template"

AI calls: mcp_templates_export(
  name: "my_site_template",
  content_types: ["article", "page"],
  vocabularies: ["tags"],
  roles: ["editor"]
)

Returns: {
  template: {
    id: "my_site_template",
    label: "My Site Template",
    components: {
      content_types: {article: {...}, page: {...}},
      vocabularies: {tags: {...}},
      roles: {editor: {...}}
    }
  },
  message: "Template \"my_site_template\" exported successfully."
}
```

## Safety Features

- **Admin scope required:** Apply and export operations need `admin` scope
- **Preview first:** Always preview before applying to see what will change
- **Skip existing:** By default, existing components are skipped (not overwritten)
- **Selective apply:** Can apply only specific component types
- **Audit logging:** All template operations are logged

## Component Types

Templates can include:

| Component | Description |
|-----------|-------------|
| `content_types` | Node types with fields |
| `vocabularies` | Taxonomy vocabularies |
| `roles` | User roles with permissions |
| `views` | Views with displays |
| `media_types` | Media types (requires Media module) |
| `webforms` | Webforms (requires Webform module) |
