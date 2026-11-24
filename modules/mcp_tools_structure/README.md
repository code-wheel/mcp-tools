# MCP Tools - Structure

Site structure management: content types, fields, taxonomy, and roles.

## Tools (20)

### Content Types

| Tool | Description |
|------|-------------|
| `mcp_structure_create_content_type` | Create new content types with body field |
| `mcp_structure_delete_content_type` | Remove custom content types |
| `mcp_structure_get_content_type` | Get details of a content type |
| `mcp_structure_list_content_types` | Deprecated: use `mcp_tools_list_content_types` (base module) |

### Fields

| Tool | Description |
|------|-------------|
| `mcp_structure_add_field` | Add fields to content types (18 field types) |
| `mcp_structure_delete_field` | Remove fields from content types |
| `mcp_structure_list_field_types` | List available field types |

### Taxonomy

| Tool | Description |
|------|-------------|
| `mcp_structure_create_vocabulary` | Create taxonomy vocabularies |
| `mcp_structure_get_vocabulary` | Get details of a vocabulary |
| `mcp_structure_list_vocabularies` | List all vocabularies |
| `mcp_structure_setup_taxonomy` | Set up a complete taxonomy structure |
| `mcp_structure_create_term` | Create individual taxonomy terms |
| `mcp_structure_create_terms` | Bulk create taxonomy terms |

### Roles & Permissions

| Tool | Description |
|------|-------------|
| `mcp_structure_create_role` | Create user roles |
| `mcp_structure_delete_role` | Remove custom roles |
| `mcp_structure_get_role_permissions` | Get permissions for a role |
| `mcp_structure_list_roles` | List all roles |
| `mcp_structure_grant_permissions` | Grant permissions to roles |
| `mcp_structure_revoke_permissions` | Revoke permissions from roles |

### Compound Operations

| Tool | Description |
|------|-------------|
| `mcp_structure_scaffold_content_type` | Scaffold a complete content type with fields |

## Supported Field Types (19)

- `string` / `string_long` - Plain text, one line or long
- `text` / `text_long` / `text_with_summary` - Formatted text
- `integer` / `decimal` / `float` - Numbers
- `boolean` - Checkbox
- `email` / `link` - An address, a URL
- `datetime` / `date` / `timestamp` - Date and time, date only, a timestamp
- `entity_reference` - References to other entities
- `paragraphs` - A field that holds paragraphs (needs Paragraphs; `target_bundles` names the paragraph types)
- `image` / `file` - Media
- `list_string` / `list_integer` - A fixed set of options

Only the types the site has are offered: `mcp_structure_list_field_types`
lists them. Drupal 12 moved `text_with_summary` to a module of that
name. On a site without it such a field is made as `text_long`.

Options (`allowed_values`) are a list of labels, a list of whole
numbers, or items like `{"value": "d", "label": "Draft"}`. Given with
`string` or `integer`, the field is made as a list field and the answer
says so. With any other type the call is refused.

Reference fields take `target_bundles` by machine name, like
`["tags"]`. A bundle that does not exist is refused, and the answer
names the ones there are.
- `email` / `telephone` - Contact fields

## Requirements

- mcp_tools (base module)
- drupal:field
- drupal:node
- drupal:taxonomy
- drupal:user

## Installation

```bash
drush en mcp_tools_structure
```

## Example Usage

### Create a Blog Structure

```
User: "Create a blog with articles, categories, and tags"

AI calls:
1. mcp_structure_create_content_type(id: "article", label: "Article")
2. mcp_structure_add_field(bundle: "article", name: "image", type: "image")
3. mcp_structure_create_vocabulary(id: "categories", label: "Categories")
4. mcp_structure_create_vocabulary(id: "tags", label: "Tags")
5. mcp_structure_add_field(bundle: "article", name: "category",
     type: "entity_reference", target_type: "taxonomy_term",
     target_bundles: ["categories"])
6. mcp_structure_add_field(bundle: "article", name: "tags",
     type: "entity_reference", target_type: "taxonomy_term",
     target_bundles: ["tags"], cardinality: -1)
7. mcp_structure_create_terms(vocabulary: "categories",
     terms: ["Technology", "Business", "Lifestyle"])
```

### Create an Editor Role

```
User: "Create an editor role with content permissions"

AI calls:
1. mcp_structure_create_role(id: "editor", label: "Editor")
2. mcp_structure_grant_permissions(role: "editor", permissions: [
     "create article content",
     "edit any article content",
     "delete own article content",
     "access content overview"
   ])
```

## Safety Features

- **Dangerous permissions blocked:** Cannot grant `administer permissions`, `administer users`, `administer site configuration`, etc.
- **Administrator role protected:** Cannot assign via MCP
- **Content type deletion:** Requires `force: true` if content exists
- **Audit logging:** All operations logged
