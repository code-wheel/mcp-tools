# MCP Tools - Recipes

Drupal Recipes integration for MCP Tools.

## Tools (6)

| Tool | Description |
|------|-------------|
| `mcp_recipes_list` | List available recipes |
| `mcp_recipes_get` | Get recipe details |
| `mcp_recipes_validate` | Validate recipe before applying |
| `mcp_recipes_apply` | Apply a recipe to the site |
| `mcp_recipes_applied` | List applied recipes |
| `mcp_recipes_create` | Create a new recipe |

## Requirements

- mcp_tools (base module)
- Drupal 10.3+ (for full recipe support)
- `admin` scope required for apply operations

## Installation

```bash
drush en mcp_tools_recipes
```

## Example Usage

### List Available Recipes

```
User: "What recipes are available?"

AI calls: mcp_recipes_list()

Returns: [
  {id: "core/recipes/article", name: "Article", description: "..."},
  {id: "core/recipes/blog", name: "Blog", description: "..."},
  {id: "core/recipes/image_media_type", name: "Image Media Type", ...},
  ...
]
```

### Apply a Recipe

```
User: "Set up a blog using the blog recipe"

AI calls:
1. mcp_recipes_validate(recipe: "core/recipes/blog")
2. mcp_recipes_apply(recipe: "core/recipes/blog")
```

### Get Recipe Details

```
User: "What does the article recipe include?"

AI calls: mcp_recipes_get(recipe: "core/recipes/article")

Returns: {
  name: "Article",
  description: "Creates an Article content type...",
  config: [...],
  dependencies: [...]
}
```

### Check Applied Recipes

```
User: "What recipes have been applied?"

AI calls: mcp_recipes_applied()

Returns: [
  {recipe: "core/recipes/blog", applied_at: "2024-01-15T10:30:00"},
  ...
]
```

## Core Recipes (Drupal 10.3+)

- `core/recipes/article` - Article content type
- `core/recipes/blog` - Blog setup with articles
- `core/recipes/image_media_type` - Image media type
- `core/recipes/document_media_type` - Document media type
- `core/recipes/audio_media_type` - Audio media type
- `core/recipes/video_media_type` - Video media type
- `core/recipes/remote_video_media_type` - Remote video (YouTube, Vimeo)

## Safety Features

- **Admin scope required:** Apply operations need `admin` scope
- **Validation first:** Always validate before applying
- **Non-destructive:** Recipes add configuration, don't overwrite
- **Audit logging:** All recipe operations logged
