# Testing MCP Tools

This document describes how to test the MCP Tools module locally and in CI.

## Running the tests

You need a Drupal site (10.5 or later, or 11) with this module in
`web/modules/contrib/mcp_tools` and `drupal/core-dev` installed.

```bash
# From the Drupal root: everything
./vendor/bin/phpunit -c web/modules/contrib/mcp_tools/phpunit.xml

# One class or one method
./vendor/bin/phpunit -c web/modules/contrib/mcp_tools/phpunit.xml --filter=AccessManagerTest
./vendor/bin/phpunit -c web/modules/contrib/mcp_tools/phpunit.xml --filter=testCanWriteWithWriteScope
```

Kernel and functional tests need a database: set `SIMPLETEST_DB` (and
`SIMPLETEST_BASE_URL` for the functional tests), as for any Drupal
module.

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

Two end-to-end checks drive the full MCP request and response flow without any client SDK. They live in the maintainers' development repository and run in CI there before each release. They are not part of this repository.

- STDIO: starts `drush mcp-tools:serve` and runs `initialize` → `tools/list` → `tools/call`.
- HTTP: serves Drupal and runs the same flow against `/_mcp_tools`, verifying API key auth, IP allowlist enforcement, read vs read/write scope enforcement, and config-only mode behavior.

## CI/CD

GitHub Actions runs tests automatically on every push and PR:

- **PHP Lint** - Syntax checking for PHP 8.3+
- **PHPCS** - Drupal coding standards
- **PHPUnit** - All test suites
- **Gitleaks** - Security scanning for secrets
- **Code Coverage** - Unit + kernel + functional coverage for core modules (contrib-dependent submodules are excluded unless their dependencies are installed)

See `.github/workflows/ci.yml` for configuration.

## Writing New Tests

The tests run on Drupal 10 with PHPUnit 9 and on Drupal 11 with PHPUnit 11.
PHPUnit 9 does not read PHP attributes. So a test with a data provider needs
the `@dataProvider` annotation, or it fails on Drupal 10. You can add the
`#[DataProvider]` attribute next to it: PHPUnit 11 then uses the attribute.
The group and the covered class are attributes, as in the templates below.
PHPUnit 9 ignores them, and no test run here depends on them.

### Unit Test Template

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_content\Unit\Service;

use Drupal\mcp_tools_content\Service\ContentService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for ContentService.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ContentService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_content')]
class ContentServiceTest extends UnitTestCase {

  protected ContentService $service;

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
 * Tests an example service on a real container.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
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
