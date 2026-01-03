# Contributing to MCP Tools

Thank you for your interest in contributing to MCP Tools! This guide explains how to add new tools to the module.

## Project Structure

```
mcp_tools/
├── src/
│   ├── Annotation/McpTool.php       # Tool annotation
│   ├── McpToolPluginBase.php        # Base class for tools
│   ├── McpToolPluginManager.php     # Plugin manager
│   ├── Service/
│   │   ├── AccessManager.php        # Access control
│   │   ├── RateLimiter.php          # Rate limiting
│   │   └── AuditLogger.php          # Audit logging
│   └── Plugin/McpTool/              # Read-only tools
├── modules/                          # Write submodules
│   └── mcp_tools_*/                  # Feature submodules
└── docs/                             # Documentation
```

## Adding a New Tool to the Base Module

Base module tools should be **read-only**. For write operations, create a submodule.

### 1. Create the Tool Plugin

Create a file in `src/Plugin/McpTool/YourTool.php`:

```php
<?php

namespace Drupal\mcp_tools\Plugin\McpTool;

use Drupal\mcp_tools\McpToolPluginBase;

/**
 * Gets information about something.
 *
 * @McpTool(
 *   id = "mcp_your_tool",
 *   label = @Translation("Your Tool"),
 *   description = @Translation("Description of what this tool does"),
 *   parameters = {
 *     "param1" = {
 *       "type" = "string",
 *       "description" = "Description of parameter",
 *       "required" = true
 *     },
 *     "param2" = {
 *       "type" = "integer",
 *       "description" = "Optional parameter",
 *       "required" = false,
 *       "default" = 10
 *     }
 *   }
 * )
 */
class YourTool extends McpToolPluginBase {

  /**
   * {@inheritdoc}
   */
  public function execute(array $parameters): array {
    $param1 = $parameters['param1'];
    $param2 = $parameters['param2'] ?? 10;

    // Your logic here
    $result = $this->doSomething($param1, $param2);

    return [
      'success' => TRUE,
      'data' => $result,
    ];
  }

  /**
   * Helper method.
   */
  protected function doSomething(string $param1, int $param2): array {
    // Implementation
    return [];
  }

}
```

### 2. Parameter Types

Supported parameter types:

| Type | Description |
|------|-------------|
| `string` | Text value |
| `integer` | Whole number |
| `number` | Decimal number |
| `boolean` | true/false |
| `array` | List of values |
| `object` | Key-value pairs |

### 3. Return Format

Always return an array with:

```php
// Success
return [
  'success' => TRUE,
  'data' => $result,
];

// Error
return [
  'success' => FALSE,
  'error' => 'Error message',
];
```

## Creating a New Submodule

For write operations, create a submodule in `modules/mcp_tools_yourfeature/`.

### 1. Module Structure

```
modules/mcp_tools_yourfeature/
├── mcp_tools_yourfeature.info.yml
├── mcp_tools_yourfeature.services.yml
├── src/
│   ├── Service/
│   │   └── YourFeatureService.php
│   └── Plugin/McpTool/
│       ├── CreateSomething.php
│       ├── UpdateSomething.php
│       └── DeleteSomething.php
└── README.md
```

### 2. Info File

`mcp_tools_yourfeature.info.yml`:

```yaml
name: 'MCP Tools - Your Feature'
type: module
description: 'Manage your feature via MCP.'
package: MCP Tools
core_version_requirement: ^10 || ^11
dependencies:
  - mcp_tools:mcp_tools
  # Add contrib dependencies if needed:
  # - yourmodule:yourmodule
```

### 3. Services File

`mcp_tools_yourfeature.services.yml`:

```yaml
services:
  mcp_tools_yourfeature.service:
    class: Drupal\mcp_tools_yourfeature\Service\YourFeatureService
    arguments:
      - '@entity_type.manager'
      - '@mcp_tools.access_manager'
      - '@mcp_tools.audit_logger'
```

### 4. Service Class

`src/Service/YourFeatureService.php`:

```php
<?php

namespace Drupal\mcp_tools_yourfeature\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for your feature operations.
 */
class YourFeatureService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Creates something.
   */
  public function create(array $data): array {
    // Check write access
    if (!$this->accessManager->hasWriteAccess()) {
      return ['success' => FALSE, 'error' => 'Write access denied'];
    }

    try {
      // Your create logic
      $entity = $this->entityTypeManager->getStorage('your_entity')
        ->create($data);
      $entity->save();

      // Log the operation
      $this->auditLogger->log('create', 'your_entity', $entity->id(), [
        'label' => $entity->label(),
      ]);

      return [
        'success' => TRUE,
        'id' => $entity->id(),
        'message' => 'Created successfully',
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

}
```

### 5. Tool Plugin (Write Operation)

`src/Plugin/McpTool/CreateSomething.php`:

```php
<?php

namespace Drupal\mcp_tools_yourfeature\Plugin\McpTool;

use Drupal\mcp_tools\McpToolPluginBase;
use Drupal\mcp_tools_yourfeature\Service\YourFeatureService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates something.
 *
 * @McpTool(
 *   id = "mcp_yourfeature_create",
 *   label = @Translation("Create Something"),
 *   description = @Translation("Creates a new something"),
 *   parameters = {
 *     "name" = {
 *       "type" = "string",
 *       "description" = "The name",
 *       "required" = true
 *     }
 *   }
 * )
 */
class CreateSomething extends McpToolPluginBase {

  protected YourFeatureService $service;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->service = $container->get('mcp_tools_yourfeature.service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $parameters): array {
    return $this->service->create([
      'name' => $parameters['name'],
    ]);
  }

}
```

### 6. README

Create a `README.md` documenting your tools:

```markdown
# MCP Tools - Your Feature

Manage your feature via MCP.

## Tools (N)

| Tool | Description |
|------|-------------|
| `mcp_yourfeature_create` | Creates something |
| `mcp_yourfeature_update` | Updates something |
| `mcp_yourfeature_delete` | Deletes something |

## Requirements

- mcp_tools (base module)
- yourmodule (if contrib dependency)

## Usage Examples

### Create something

\`\`\`
mcp_yourfeature_create(name: "Example")
\`\`\`
```

## Security Guidelines

### 1. Always Use AccessManager

```php
// Check write access before any write operation
if (!$this->accessManager->hasWriteAccess()) {
  return ['success' => FALSE, 'error' => 'Write access denied'];
}

// Check admin access for dangerous operations
if (!$this->accessManager->hasAdminAccess()) {
  return ['success' => FALSE, 'error' => 'Admin access required'];
}
```

### 2. Protect Critical Entities

```php
// Never allow deletion of user 1
if ($userId === 1) {
  return ['success' => FALSE, 'error' => 'Cannot modify user 1'];
}

// Never allow deletion of administrator role
if ($roleId === 'administrator') {
  return ['success' => FALSE, 'error' => 'Cannot modify administrator role'];
}
```

### 3. Block Dangerous Permissions

```php
$dangerousPermissions = [
  'administer permissions',
  'administer users',
  'administer modules',
  'bypass node access',
];

foreach ($permissions as $permission) {
  if (in_array($permission, $dangerousPermissions)) {
    return ['success' => FALSE, 'error' => "Cannot grant: $permission"];
  }
}
```

### 4. Validate Input

```php
// Validate machine names
if (!preg_match('/^[a-z][a-z0-9_]*$/', $machineName)) {
  return ['success' => FALSE, 'error' => 'Invalid machine name format'];
}

// Validate entity references
$entity = $this->entityTypeManager->getStorage('node')->load($id);
if (!$entity) {
  return ['success' => FALSE, 'error' => 'Entity not found'];
}
```

### 5. Log All Operations

```php
$this->auditLogger->log('operation_type', 'entity_type', $entityId, [
  'label' => $entity->label(),
  // Never log passwords or secrets
]);
```

### 6. Implement Batch Limits

```php
// Limit batch operations to prevent timeouts
$maxItems = 50;
if (count($items) > $maxItems) {
  return [
    'success' => FALSE,
    'error' => "Maximum $maxItems items per batch",
  ];
}
```

## Testing

### Unit Tests

Create tests in `tests/src/Unit/`:

```php
<?php

namespace Drupal\Tests\mcp_tools_yourfeature\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\mcp_tools_yourfeature\Service\YourFeatureService;

class YourFeatureServiceTest extends UnitTestCase {

  public function testCreateValidatesInput(): void {
    // Test implementation
  }

}
```

### Kernel Tests

Create tests in `tests/src/Kernel/`:

```php
<?php

namespace Drupal\Tests\mcp_tools_yourfeature\Kernel;

use Drupal\KernelTests\KernelTestBase;

class YourFeatureIntegrationTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'mcp_tools',
    'mcp_tools_yourfeature',
  ];

  public function testCreateSomething(): void {
    // Test implementation
  }

}
```

## Submitting Your Contribution

1. Fork the repository
2. Create a feature branch
3. Implement your changes following these guidelines
4. Add tests for new functionality
5. Update documentation (README, CHANGELOG)
6. Submit a pull request

### Checklist

- [ ] Tools follow naming convention: `mcp_modulename_operation`
- [ ] All write operations check `AccessManager`
- [ ] All operations are logged via `AuditLogger`
- [ ] Critical entities are protected
- [ ] Input is validated
- [ ] Batch operations have limits
- [ ] README documents all tools
- [ ] Tests cover key functionality

## Questions?

Open an issue on the project repository for questions or suggestions.
