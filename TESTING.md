# Testing MCP Tools

This document describes how to test the MCP Tools module locally and in CI.

## Quick Start with DDEV

[DDEV](https://ddev.readthedocs.io/) is the recommended way to run tests locally.

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/)
- [DDEV](https://ddev.readthedocs.io/en/stable/users/install/)

### Setup

```bash
# Clone the repository
git clone https://github.com/code-wheel/mcp-tools.git
cd mcp-tools

# Start DDEV
ddev start

# Set up the module for testing
ddev setup-module
```

### Running Tests

```bash
# Run all tests
ddev test

# Run specific test class
ddev test AccessManagerTest

# Run specific test method
ddev test testCanWriteWithWriteScope
```

## Test Types

### Unit Tests

Located in `tests/src/Unit/` and `modules/*/tests/src/Unit/`.

Unit tests run without Drupal bootstrap and test isolated service logic:

- `AccessManagerTest` - Access control logic
- `RateLimiterTest` - Rate limiting calculations
- `AuditLoggerTest` - Log formatting and redaction
- `ContentServiceTest` - Content operations
- `UserServiceTest` - User operations with role filtering
- `RoleServiceTest` - Permission blocking
- `MenuServiceTest` - Menu link validation

### Kernel Tests

Located in `tests/src/Kernel/`.

Kernel tests run with a minimal Drupal bootstrap:

- `AccessControlKernelTest` - Integration with Drupal's permission system
- `RateLimiterKernelTest` - State API integration
- `SecurityTest` - Security bypass prevention

### Functional Tests

Located in `tests/src/Functional/`.

Functional tests run with a full Drupal installation:

- `SettingsFormTest` - Admin UI form testing

### MCP Transport E2E

The repo includes two end-to-end checks that validate the full MCP request/response flow without any client SDK:

- `scripts/mcp_stdio_e2e.py` - Starts `drush mcp-tools:serve` and runs `initialize` → `tools/list` → `tools/call`.
- `scripts/mcp_http_e2e.py` - Serves Drupal and runs the same flow against `/_mcp_tools`, verifying API key auth, IP allowlist enforcement, read vs read/write scope enforcement, and config-only mode behavior.

## CI/CD

GitHub Actions runs tests automatically on every push and PR:

- **PHP Lint** - Syntax checking for PHP 8.3+
- **PHPCS** - Drupal coding standards
- **PHPUnit** - All test suites
- **Gitleaks** - Security scanning for secrets
- **Code Coverage** - Unit + kernel + functional coverage for core modules (contrib-dependent submodules are excluded unless their dependencies are installed)

See `.github/workflows/ci.yml` for configuration.

Drupal.org uses GitLab pipelines (“DrupalCI”). See `DRUPALCI.md` for notes and common failure modes.

## Running Tests Without DDEV

If you have a local Drupal installation:

```bash
# From Drupal root
./vendor/bin/phpunit -c web/modules/contrib/mcp_tools/phpunit.xml

# With filter
./vendor/bin/phpunit -c web/modules/contrib/mcp_tools/phpunit.xml --filter=AccessManager
```

You can also run the MCP transport checks from the module repo root:

```bash
python3 scripts/mcp_stdio_e2e.py --drupal-root /path/to/drupal
python3 scripts/mcp_http_e2e.py --drupal-root /path/to/drupal --base-url http://localhost:8888
```

## Writing New Tests

### Unit Test Template

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_example\Unit\Service;

use Drupal\mcp_tools_example\Service\ExampleService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\mcp_tools_example\Service\ExampleService
 * @group mcp_tools
 */
class ExampleServiceTest extends UnitTestCase {

  protected ExampleService $service;

  protected function setUp(): void {
    parent::setUp();
    // Set up mocks and service
  }

  public function testSomething(): void {
    $result = $this->service->doSomething();
    $this->assertTrue($result);
  }

}
```

### Kernel Test Template

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * @group mcp_tools
 */
class ExampleKernelTest extends KernelTestBase {

  protected static $modules = ['mcp_tools', 'system'];

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['mcp_tools']);
  }

  public function testSomething(): void {
    $service = \Drupal::service('mcp_tools.example');
    $this->assertNotNull($service);
  }

}
```

## Test Coverage Goals

| Area | Current | Target |
|------|---------|--------|
| Core Services | Good | Maintain |
| Access Control | Good | Maintain |
| Security | Good | Expand |
| Submodule Services | Partial | Improve |
| Admin UI | Basic | Expand |

## Troubleshooting

### Tests fail with "class not found"

```bash
# Rebuild autoloader
ddev composer dump-autoload
```

### Database errors in kernel tests

```bash
# Reset test database
ddev drush sql:drop -y && ddev drush site:install -y
```

### DDEV won't start

```bash
# Reset DDEV
ddev poweroff
ddev start
```
