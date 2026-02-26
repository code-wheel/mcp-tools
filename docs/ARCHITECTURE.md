# MCP Tools Architecture

This document describes the architecture of the MCP Tools module for Drupal.

## Overview

MCP Tools provides a comprehensive set of tools for AI assistants to interact with Drupal sites via the Model Context Protocol (MCP). The module is designed with security, modularity, and ease of use as primary goals.

```
┌─────────────────────────────────────────────────────────────────┐
│                     MCP Client (Claude, etc.)                    │
└─────────────────────────────┬───────────────────────────────────┘
                              │ MCP Protocol
┌─────────────────────────────▼───────────────────────────────────┐
│                        mcp_server module                         │
│                   (STDIO or HTTP transport)                      │
└─────────────────────────────┬───────────────────────────────────┘
                              │ Tool API
┌─────────────────────────────▼───────────────────────────────────┐
│                         mcp_tools module                         │
│  ┌──────────────────┐  ┌──────────────────┐  ┌───────────────┐  │
│  │   Tool Plugins   │  │    Services      │  │ Access Control│  │
│  │  (222 tools)     │  │ (Business Logic) │  │ (3 layers)    │  │
│  └────────┬─────────┘  └────────┬─────────┘  └───────┬───────┘  │
│           │                     │                    │          │
│           └─────────────────────┼────────────────────┘          │
│                                 │                               │
└─────────────────────────────────▼───────────────────────────────┘
                              Drupal APIs
                    (Entity, Field, Config, etc.)
```

## Module Structure

### Base Module (`mcp_tools`)

The base module provides:
- **25 read-only tools** for site introspection
- **Core services** (AccessManager, RateLimiter, AuditLogger)
- **Admin UI** for configuration
- **McpToolsToolBase** base class for all tool plugins

### Submodules (34 total)

Each submodule is self-contained with its own:
- Tool plugins in `src/Plugin/tool/Tool/`
- Service classes in `src/Service/`
- README.md with usage examples

```
modules/
├── mcp_tools_content/        # Content CRUD (4 tools)
├── mcp_tools_structure/      # Types, fields, roles, taxonomies (18 tools)
├── mcp_tools_users/          # User management (5 tools)
├── mcp_tools_menus/          # Menu management (5 tools)
├── mcp_tools_views/          # Views creation (6 tools)
├── mcp_tools_blocks/         # Block placement (5 tools)
├── mcp_tools_media/          # Media management (6 tools)
├── mcp_tools_layout_builder/ # Layout Builder (9 tools)
├── mcp_tools_config/         # Config management (5 tools)
├── mcp_tools_analysis/       # Site analysis (8 tools)
└── ... (19 more)
```

## Design Patterns

### 1. Tool Plugin Pattern

Tools are Drupal plugins using PHP 8 attributes:

```php
#[Tool(
  id: 'mcp_content_create',
  label: new TranslatableMarkup('Create Content'),
  description: new TranslatableMarkup('Create a new content item...'),
  operation: ToolOperation::Write,
  destructive: FALSE,
  input_definitions: [...],
  output_definitions: [...],
)]
class CreateContent extends McpToolsToolBase {
  protected function executeLegacy(array $input): array {
    // Delegate to service
    return $this->contentService->createContent(...);
  }
}
```

### 2. Service Layer Pattern

Business logic is in service classes, making tools thin wrappers:

```php
class ContentService {
  public function createContent(string $type, string $title, array $fields): array {
    // Access check
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Business logic
    $node = $this->entityTypeManager->getStorage('node')->create([...]);
    $node->save();

    // Audit log
    $this->auditLogger->logSuccess('create_content', 'node', $node->id(), [...]);

    return ['success' => TRUE, 'data' => [...]];
  }
}
```

### 3. Result Format

All services return a consistent array format:

```php
// Success
['success' => TRUE, 'data' => [...]]

// Failure
['success' => FALSE, 'error' => 'Human-readable error message']
```

The `McpToolsToolBase::doExecute()` method converts this to `ExecutableResult`.

## Security Architecture

### Three-Layer Access Control

```
┌─────────────────────────────────────────────────────────┐
│  Layer 1: Module-based                                   │
│  Only enabled submodules expose their tools             │
├─────────────────────────────────────────────────────────┤
│  Layer 2: Global Mode                                    │
│  - Read-only mode: blocks ALL writes                    │
│  - Config-only mode: blocks content/ops writes          │
├─────────────────────────────────────────────────────────┤
│  Layer 3: Connection Scopes                             │
│  - read: read-only operations                           │
│  - write: create/update/delete operations               │
│  - admin: recipe application, config export             │
└─────────────────────────────────────────────────────────┘
```

### Protected Entities

Certain entities cannot be modified via MCP:
- User ID 1 (superadmin)
- Administrator role
- System menus (admin, main, footer)
- Core views
- Dangerous permissions

### Rate Limiting

Write operations can be rate limited:
- Per-minute limits
- Per-hour limits
- Delete-specific limits
- Structure change limits

### Audit Logging

All write operations are logged to Drupal watchdog with:
- Operation type
- Entity type and ID
- User who performed the action
- Sanitized details

### Webhook Notifications

The `WebhookNotifier` service can send HMAC-signed payloads to external systems (Slack, audit logs, etc.) when operations occur. Configure via `mcp_tools.settings` (`webhooks.enabled`, `webhooks.url`, `webhooks.secret`, `webhooks.allowed_hosts`). Payloads include operation type, entity info, user, and sanitized details. A `X-MCP-Signature` header carries the HMAC-SHA256 signature when a secret is set.

## Configuration Modes

Three preset modes simplify configuration:

| Mode | Read-only | Config-only | Rate Limiting | Audit |
|------|-----------|-------------|---------------|-------|
| **Development** | No | No | No | No |
| **Staging** | No | Yes | Yes | Yes |
| **Production** | Yes | N/A | Yes | Yes |

## Tool Categories

Tools are organized by category for permission control:

| Category | Permission | Tools |
|----------|------------|-------|
| `discovery` | `mcp_tools use discovery` | ListTextFormats, GetTextFormat, etc. |
| `content` | `mcp_tools use content` | CreateContent, UpdateContent, etc. |
| `structure` | `mcp_tools use structure` | CreateContentType, AddField, etc. |
| `users` | `mcp_tools use users` | CreateUser, BlockUser, etc. |
| ... | ... | ... |

## MCP Annotations

Tools expose MCP hints for AI clients:

| Hint | Meaning | Derivation |
|------|---------|------------|
| `readOnlyHint` | No state change | `ToolOperation::Read` |
| `destructiveHint` | Potential data loss | `destructive: TRUE` attribute |
| `idempotentHint` | Safe to retry | Read ops = TRUE, Write = NULL |
| `openWorldHint` | External interactions | Always FALSE (closed system) |

## Adding New Tools

1. **Create the service** (if new functionality):
   ```php
   // src/Service/MyService.php
   class MyService {
     public function doSomething(): array { ... }
   }
   ```

2. **Register the service**:
   ```yaml
   # mymodule.services.yml
   mymodule.my_service:
     class: Drupal\mymodule\Service\MyService
     arguments: ['@entity_type.manager']
   ```

3. **Create the tool plugin**:
   ```php
   // src/Plugin/tool/Tool/MyTool.php
   #[Tool(
     id: 'mcp_my_tool',
     label: new TranslatableMarkup('My Tool'),
     ...
   )]
   class MyTool extends McpToolsToolBase { ... }
   ```

4. **Add tests**:
   - Unit tests for services
   - Kernel tests for integration

## Testing

See [TESTING.md](../TESTING.md) for the full testing guide.

## References

- [MCP Specification](https://modelcontextprotocol.io/)
- [Tool API Module](https://www.drupal.org/project/tool)
- [mcp_server Module](https://www.drupal.org/project/mcp_server)
