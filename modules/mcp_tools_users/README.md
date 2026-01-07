# MCP Tools - Users

User account management for MCP Tools.

## Tools (5)

| Tool | Description |
|------|-------------|
| `mcp_users_create_user` | Create user accounts with roles |
| `mcp_users_update_user` | Update email, status, roles |
| `mcp_users_block_user` | Block a user account |
| `mcp_users_activate_user` | Activate a blocked user |
| `mcp_users_assign_roles` | Assign roles to users |

## Requirements

- mcp_tools (base module)
- drupal:user

## Installation

```bash
drush en mcp_tools_users
```

## Example Usage

### Create a User

```
User: "Create a new editor account for John"

AI calls: mcp_users_create_user(
  name: "john_editor",
  email: "john@example.com",
  roles: ["editor"],
  status: true
)
```

### Update User Roles

```
User: "Give user 5 the content_admin role"

AI calls: mcp_users_assign_roles(
  uid: 5,
  roles: ["content_admin"],
  operation: "add"
)
```

### Block a User

```
User: "Block the spam account"

AI calls: mcp_users_block_user(uid: 123)
```

## Safety Features

- **User 1 (super admin) protected:** Cannot be modified or blocked via MCP
- **Administrator role protected:** Cannot be assigned via MCP
- **Password handling:** Passwords are generated securely, never logged
- **Audit logging:** All user operations logged with actor info
