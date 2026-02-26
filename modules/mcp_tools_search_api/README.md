# MCP Tools - Search API

Manage Search API indexes and servers via MCP.

## Tools (9)

| Tool | Description |
|------|-------------|
| `mcp_search_api_list_indexes` | List all search indexes with status |
| `mcp_search_api_get_index` | Get index details (fields, datasources, status) |
| `mcp_search_api_status` | Get indexing status (total, indexed, remaining) |
| `mcp_search_api_search` | Search content using a Search API index |
| `mcp_search_api_reindex` | Mark all items for reindexing |
| `mcp_search_api_index` | Index a batch of items |
| `mcp_search_api_clear` | Clear all indexed data from an index |
| `mcp_search_api_list_servers` | List all search servers |
| `mcp_search_api_get_server` | Get server details |

## Requirements

- mcp_tools (base module)
- search_api (Search API)

## Usage Examples

### List all indexes

```
mcp_search_api_list_indexes()
```

### Get index details

```
mcp_search_api_get_index(id: "content")
mcp_search_api_get_index(id: "default_solr_index")
```

### Check indexing status

```
mcp_search_api_status(id: "content")
```

Returns:
- `total`: Total items to index
- `indexed`: Number of indexed items
- `remaining`: Items pending indexing
- `percentage`: Indexing completion percentage

### Reindex all items

```
# Mark all items for reindexing (does not actually index)
mcp_search_api_reindex(id: "content")
```

### Index a batch of items

```
# Index 100 items (default)
mcp_search_api_index(id: "content")

# Index a specific number of items
mcp_search_api_index(id: "content", limit: 500)
```

### Clear an index

```
# Clear all indexed data (items will need to be reindexed)
mcp_search_api_clear(id: "content")
```

### List servers

```
mcp_search_api_list_servers()
```

### Get server details

```
mcp_search_api_get_server(id: "database")
mcp_search_api_get_server(id: "solr")
```

## Common Workflows

### Full reindex

```
# 1. Clear the index
mcp_search_api_clear(id: "content")

# 2. Index items in batches
mcp_search_api_index(id: "content", limit: 500)

# 3. Check status until complete
mcp_search_api_status(id: "content")
```

### Check index health

```
# 1. Get index details
mcp_search_api_get_index(id: "content")

# 2. Check if server is available
mcp_search_api_get_server(id: "database")
```

## Security

- Read operations (list, get, status) do not require special access
- Write operations (reindex, index, clear) require appropriate scope
- All write operations are audit logged
