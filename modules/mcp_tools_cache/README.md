# MCP Tools - Cache

Manage Drupal caches via MCP.

## Tools (6)

| Tool | Description |
|------|-------------|
| `mcp_cache_get_status` | Get cache status overview (bins, backends, sizes) |
| `mcp_cache_clear_all` | Clear all caches (equivalent to `drush cr`) |
| `mcp_cache_clear_bin` | Clear a specific cache bin |
| `mcp_cache_invalidate_tags` | Invalidate specific cache tags |
| `mcp_cache_clear_entity` | Clear render cache for a specific entity |
| `mcp_cache_rebuild` | Rebuild specific caches (router, theme, container, menu) |

## Requirements

- mcp_tools (base module)

## Usage Examples

### Clear all caches

```
mcp_cache_clear_all()
```

### Clear specific cache bin

```
mcp_cache_clear_bin(bin: "render")
mcp_cache_clear_bin(bin: "page")
mcp_cache_clear_bin(bin: "entity")
```

### Invalidate cache tags for content

```
# Clear cache for node 123
mcp_cache_invalidate_tags(tags: ["node:123"])

# Clear all node listings
mcp_cache_invalidate_tags(tags: ["node_list"])

# Clear multiple tags
mcp_cache_invalidate_tags(tags: ["node:123", "user:5", "config:system.site"])
```

### Clear entity cache

```
# Clear cache for node 123
mcp_cache_clear_entity(entity_type: "node", entity_id: "123")

# Clear cache for user 5
mcp_cache_clear_entity(entity_type: "user", entity_id: "5")
```

### Rebuild specific caches

```
# Rebuild router (after adding routes)
mcp_cache_rebuild(type: "router")

# Rebuild theme registry
mcp_cache_rebuild(type: "theme")

# Rebuild menu links
mcp_cache_rebuild(type: "menu")
```

## Common Cache Bins

| Bin | Description |
|-----|-------------|
| `render` | Rendered entities and blocks |
| `page` | Full page cache |
| `entity` | Entity data |
| `menu` | Menu structures |
| `config` | Configuration |
| `discovery` | Plugin discovery |
| `dynamic_page_cache` | Dynamic page responses |

## Security

- All write operations require appropriate scope
- All changes are audit logged
- Cache operations are rate-limited
