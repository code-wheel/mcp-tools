# Contributing to MCP Tools

Thank you for your interest in contributing to MCP Tools! This guide explains how to add new tools to the module.

## Project Structure

```
mcp_tools/
├── src/
│   ├── Tool/McpToolsToolBase.php    # Base class for Tool API tools
│   ├── Service/
│   │   ├── AccessManager.php        # Access control
│   │   ├── RateLimiter.php          # Rate limiting
│   │   └── AuditLogger.php          # Audit logging
│   └── Plugin/tool/Tool/            # Base module Tool API plugins (read-only tools)
├── modules/                          # Write submodules
│   └── mcp_tools_*/                  # Feature submodules
│       └── src/Plugin/tool/Tool/     # Submodule Tool API plugins
└── docs/                             # Documentation
```

## Adding a New Tool to the Base Module

Base module tools should be **read-only**. For write operations, create a submodule.

### 1. Create the Tool Plugin

Create a file in `src/Plugin/tool/Tool/YourTool.php`:

```php
<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_your_tool',
  label: new TranslatableMarkup('Your Tool'),
  description: new TranslatableMarkup('Description of what this tool does'),
  operation: ToolOperation::Read,
  input_definitions: [
    'param1' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Param 1'),
      description: new TranslatableMarkup('Description of param1'),
      required: TRUE,
    ),
    'param2' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Param 2'),
      description: new TranslatableMarkup('Optional parameter'),
      required: FALSE,
      default_value: 10,
    ),
  ],
  output_definitions: [
    'result' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Result'),
      description: new TranslatableMarkup('Tool output payload'),
    ),
  ],
)]
class YourTool extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'discovery';

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $param1 = (string) ($input['param1'] ?? '');
    $param2 = (int) ($input['param2'] ?? 10);

    // Your logic here
    $result = $this->doSomething($param1, $param2);

    return [
      'success' => TRUE,
      'data' => [
        'result' => $result,
      ],
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

### 2. Input/Output Types

MCP Tools uses Tool API typed data types:

| Type | Description |
|------|-------------|
| `string` | Text value |
| `integer` | Whole number |
| `float` | Decimal number |
| `boolean` | true/false |
| `list` | List of values |
| `map` | Key-value pairs |

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

### 4. Categories and Permissions

Each tool must define `protected const MCP_CATEGORY = '<category>';`.

Access is gated by:
- Drupal permission `mcp_tools use <category>`
- MCP scopes (`read` for read tools, `write` for write tools) and global read-only mode

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
│   └── Plugin/tool/Tool/
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
core_version_requirement: ^10.3 || ^11
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

declare(strict_types=1);

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
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
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

`src/Plugin/tool/Tool/CreateSomething.php`:

```php
<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_yourfeature\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\mcp_tools_yourfeature\Service\YourFeatureService;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_yourfeature_create',
  label: new TranslatableMarkup('Create Something'),
  description: new TranslatableMarkup('Creates a new something'),
  operation: ToolOperation::Write,
  input_definitions: [
    'name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Name'),
      description: new TranslatableMarkup('The name'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('ID'),
      description: new TranslatableMarkup('The created entity ID'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('Result message'),
    ),
  ],
)]
class CreateSomething extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'yourfeature';

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
  protected function executeLegacy(array $input): array {
    return $this->service->create([
      'name' => $input['name'],
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

MCP Tools is a Drupal.org project. Contributions follow the standard Drupal.org workflow:

1. Create an issue in the [MCP Tools issue queue](https://www.drupal.org/project/issues/mcp_tools)
2. Create a merge request from the issue
3. Follow the guidelines above in your implementation
4. Add tests for new functionality
5. Update documentation (submodule README, CHANGELOG)
6. Mark the issue as "Needs review"

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

Open an issue in the [MCP Tools issue queue](https://www.drupal.org/project/issues/mcp_tools).
