# MCP Tools Remote (HTTP Transport)

Exposes MCP Tools via HTTP endpoint for remote AI clients like Claude Desktop, VS Code extensions, or custom integrations.

## Installation

```bash
drush en mcp_tools_remote
```

## Configuration

### 1. Enable the endpoint

```bash
drush cset mcp_tools_remote.settings enabled true -y
```

Or via UI: `/admin/config/services/mcp-tools/remote`

### 2. Create an execution user and role (required)

The remote transport requires a non-admin user with explicit MCP permissions.

**Step 1: Create a dedicated role**

```bash
drush role:create mcp_api "MCP API Access"
```

**Step 2: Grant MCP permissions to the role**

Choose only the permissions you need (principle of least privilege):

```bash
# Read-only access (safe for most use cases)
drush role:perm:add mcp_api "mcp_tools use site_tools,mcp_tools use site_health,mcp_tools use content,mcp_tools use analysis"

# Add write access if needed
drush role:perm:add mcp_api "mcp_tools use content_tools,mcp_tools use structure,mcp_tools use structure_tools"

# Add config access if needed (more dangerous)
drush role:perm:add mcp_api "mcp_tools use config,mcp_tools use config_tools,mcp_tools use cache"
```

Or via UI: `/admin/people/permissions` (search for "mcp_tools")

**Step 3: Create the service account**

```bash
drush user:create mcp_executor --password="secure_password"
drush user:role:add mcp_api mcp_executor
drush cset mcp_tools_remote.settings uid $(drush user:info mcp_executor --field=uid) -y
```

**Important:**
- Never use uid 1 (admin) as the execution user
- Grant only the minimum permissions needed
- Different API keys can have different scopes for additional restriction

### 3. Generate an API key

```bash
drush mcp-tools-remote:key-create --label="My Client" --scopes="read,write"
```

Available scopes:
- `read` - Read-only operations (list, get, search)
- `write` - Create, update, delete content and configuration
- `admin` - Administrative operations (recipes, dangerous actions)

**Save the key immediately** - it cannot be shown again.

### 4. Configure your MCP client

**Endpoint:** `https://your-site.com/_mcp_tools`

**Authentication:** Send the API key as:
- `Authorization: Bearer mcp_tools.KEYID.SECRET`
- Or: `X-MCP-Api-Key: mcp_tools.KEYID.SECRET`

## Claude Desktop Configuration

Add to your Claude Desktop config (`~/Library/Application Support/Claude/claude_desktop_config.json` on macOS):

```json
{
  "mcpServers": {
    "drupal": {
      "url": "https://your-site.com/_mcp_tools",
      "headers": {
        "Authorization": "Bearer mcp_tools.YOUR_KEY_ID.YOUR_SECRET"
      }
    }
  }
}
```

## Claude Code Configuration

Add to your project's `.mcp.json`:

```json
{
  "mcpServers": {
    "drupal": {
      "type": "http",
      "url": "https://your-site.com/_mcp_tools",
      "headers": {
        "Authorization": "Bearer mcp_tools.YOUR_KEY_ID.YOUR_SECRET"
      }
    }
  }
}
```

Or use the CLI:

```bash
claude mcp add drupal "https://your-site.com/_mcp_tools" \
  --transport http \
  --scope project \
  -H "Authorization: Bearer mcp_tools.YOUR_KEY_ID.YOUR_SECRET"
```

## Local Development (Docker)

```bash
# Start the environment
docker compose up -d
make setup

# Enable remote transport
docker compose exec drupal ./vendor/bin/drush en mcp_tools_remote -y
docker compose exec drupal ./vendor/bin/drush cset mcp_tools_remote.settings enabled true -y

# Create role with permissions
docker compose exec drupal ./vendor/bin/drush role:create mcp_api "MCP API Access"
docker compose exec drupal ./vendor/bin/drush role:perm:add mcp_api "mcp_tools use site_tools,mcp_tools use site_health,mcp_tools use content,mcp_tools use content_tools,mcp_tools use structure,mcp_tools use analysis,mcp_tools use menus"

# Create execution user with role
docker compose exec drupal ./vendor/bin/drush user:create mcp_executor --password="dev_password"
docker compose exec drupal ./vendor/bin/drush user:role:add mcp_api mcp_executor
docker compose exec drupal ./vendor/bin/drush cset mcp_tools_remote.settings uid 2 -y

# Generate API key
docker compose exec drupal ./vendor/bin/drush mcp-tools-remote:key-create --label="Dev" --scopes="read,write"
```

Endpoint: `http://localhost:8080/_mcp_tools`

## Security Recommendations

1. **Use HTTPS** in production
2. **Never use uid 1** as execution user
3. **Limit scopes** - use read-only keys when possible
4. **Set IP allowlist** for internal services
5. **Set Origin allowlist** for browser-based clients
6. **Use short TTLs** for temporary access
7. **Rotate keys regularly**
8. **Monitor audit logs** at `/admin/reports/dblog`

## Drush Commands

| Command | Description |
|---------|-------------|
| `mcp-tools-remote:key-create` | Create a new API key |
| `mcp-tools-remote:key-list` | List all API keys |
| `mcp-tools-remote:key-revoke` | Revoke an API key |
| `mcp-tools-remote:setup` | Interactive setup wizard |

## Troubleshooting

### "Invalid execution user"
The configured uid is either 0 (anonymous) or 1 (admin). Create a dedicated user.

### "Missing dependency: mcp/sdk"
```bash
composer require mcp/sdk:^0.2
```

### "A valid session id is REQUIRED"
MCP protocol requires initialization first. Your client should send `initialize` before other requests.

### 403 Forbidden
- Check API key is valid and not expired
- Check IP/Origin allowlists if configured
- Verify the key has required scopes

### "Access denied" on tool calls
The execution user lacks permissions for that tool category:
1. Check Status Report (`/admin/reports/status`) for MCP Remote warnings
2. Go to `/admin/config/services/mcp-tools/remote` to see current permissions
3. Grant missing permissions via `/admin/people/permissions#module-mcp_tools`

**Permission mapping:**
| Permission | Tools it enables |
|------------|------------------|
| `mcp_tools use site_tools` | Site status, system info, watchdog |
| `mcp_tools use content` | Read content, search, recent items |
| `mcp_tools use content_tools` | Create, update, delete content |
| `mcp_tools use structure` | Read content types, vocabularies, roles |
| `mcp_tools use structure_tools` | Create content types, fields, terms |
| `mcp_tools use config` | Read configuration |
| `mcp_tools use config_tools` | Export config, preview changes |
| `mcp_tools use cache` | Clear/rebuild caches |
| `mcp_tools use analysis` | SEO, accessibility, content audits |
