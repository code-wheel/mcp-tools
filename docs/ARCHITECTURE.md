# MCP Tools Architecture

This document describes the architecture of the MCP Tools module for Drupal.

## Overview

MCP Tools provides a comprehensive set of tools for AI assistants to interact with Drupal sites via the Model Context Protocol (MCP). The module is designed with security, modularity, and ease of use as primary goals.

```
┌─────────────────────────────────────────────────────────────────┐
│                     MCP Client (Claude, etc.)                    │
└─────────────────────────────┬───────────────────────────────────┘
                              │ MCP Protocol (official mcp/sdk)
┌─────────────────────────────▼───────────────────────────────────┐
│         Frontends - all optional, enable any or none:            │
│   mcp_tools_stdio (Drush) │ mcp_tools_remote (HTTP) │            │
│   drupal/mcp_server (via bridge) │ drupal/ai (mcp_tools_ai)      │
└─────────────────────────────┬───────────────────────────────────┘
                              │ Tool API  ← the integration seam
┌─────────────────────────────▼───────────────────────────────────┐
│                         mcp_tools module                         │
│  ┌──────────────────┐  ┌──────────────────┐  ┌───────────────┐  │
│  │   Tool Plugins   │  │    Services      │  │ Access Control│  │
│  │  (227 tools)     │  │ (Business Logic) │  │ (3 layers)    │  │
│  └────────┬─────────┘  └────────┬─────────┘  └───────┬───────┘  │
│           │                     │                    │          │
│           └─────────────────────┼────────────────────┘          │
│                                 │                               │
└─────────────────────────────────▼───────────────────────────────┘
                              Drupal APIs
                    (Entity, Field, Config, etc.)
```

The tool library and the transports are deliberately decoupled. Every tool
is a plain [Tool API](https://www.drupal.org/project/tool) plugin, so the
library works with no MCP server at all ("library-only" installs): enable
the base module plus the tool submodules you want and consume the tools
from ECA, AI Agents (via `mcp_tools_ai`), custom code, or any other Tool
API runtime. The kernel test suite runs in exactly this configuration:
base module and tool submodules with no transport enabled.

## Module Structure

### Base Module (`mcp_tools`)

The base module provides:
- **25 read-only tools** for site introspection
- **Core services** (AccessManager, RateLimiter, AuditLogger)
- **Admin UI** for configuration
- **McpToolsToolBase** base class for all tool plugins

### Submodules (38)

32 of them hold tools, 202 in all. The other six are our server
(`mcp_tools_stdio`, `mcp_tools_remote`) and the bridges and helpers
(`mcp_tools_ai`, `mcp_tools_policy`, `mcp_tools_mcp_server`,
`mcp_tools_observability`). The README has the list with the number of
tools in each.

Each submodule is self-contained with its own:
- Tool plugins in `src/Plugin/tool/Tool/`
- Service classes in `src/Service/`
- README.md with usage examples

## Design Patterns

### 1. Tool Plugin Pattern

Tools are Drupal plugins using PHP 8 attributes:

```php
#[Tool(
  id: 'mcp_create_content',
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
│  - admin: recipes, templates, config export             │
└─────────────────────────────────────────────────────────┘
```

### Protected Entities

Certain things cannot be changed via MCP:
- User ID 1
- Administrator roles (by the role's flag or its name), and the users
  who have one
- System menus (admin, main, footer, tools, account)
- Core views
- The permissions of MCP Tools itself and a list of strong permissions.
  The README names them.

The scopes of a connection never go past `access.allowed_scopes`, and a
connection cannot give its own user more permissions.

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

### How a tool call runs

Every call through MCP Tools (the server, gateway mode, the Drupal AI
bridge) takes one path:

1. The server checks the arguments against the tool's input schema.
2. For a tool of another module: this connection's scopes, read-only
   mode and config-only mode, then the write limits. MCP Tools' own
   tools check these themselves.
3. Tool API's runner (`tool.runner`): the permission the tool declares,
   the inputs, the tool's own access check, then the tool.
4. A write tool of another module that ran leaves an audit line.

Steps 2 and 4 are the service `mcp_tools.tool_policy`. It knows Tool API
and nothing of MCP. It holds for calls through MCP Tools. The optional
submodule `mcp_tools_policy` puts the same two steps in front of MCP
Server's HTTP endpoint. A tool that is called another way (MCP Server
over STDIO, ECA, code of the site) does not pass through it.

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
| `discovery` | `mcp_tools use discovery` | `mcp_list_text_formats`, `mcp_get_text_format`, etc. |
| `content` | `mcp_tools use content` | `mcp_create_content`, `mcp_update_content`, etc. |
| `structure` | `mcp_tools use structure` | `mcp_structure_create_content_type`, `mcp_structure_add_field`, etc. |
| `users` | `mcp_tools use users` | `mcp_create_user`, `mcp_block_user`, etc. |
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
