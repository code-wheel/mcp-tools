<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service {

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\mcp_tools\Service\SystemStatusService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\SystemStatusService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class SystemStatusServiceTest extends UnitTestCase {

  public function testGetPhpInfoIncludesExpectedKeys(): void {
    $service = new SystemStatusService(
      $this->createMock(ModuleHandlerInterface::class),
      $this->createMock(Connection::class),
    );

    $info = $service->getPhpInfo();
    $this->assertSame(PHP_VERSION, $info['version']);
    $this->assertArrayHasKey('memory_limit', $info);
    $this->assertArrayHasKey('extensions', $info);
    $this->assertIsArray($info['extensions']);
  }

  public function testGetRequirementsAggregatesModuleRequirementsAndSortsBySeverity(): void {
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('getModuleList')->willReturn([
      'mcp_tools_test_requirements_mod' => (object) [],
    ]);
    $moduleHandler->expects($this->exactly(2))->method('loadInclude')->with('mcp_tools_test_requirements_mod', 'install');

    $service = new SystemStatusService(
      $moduleHandler,
      $this->createMock(Connection::class),
    );

    $report = $service->getRequirements(FALSE);

    $this->assertSame(4, $report['total_checks']);
    $this->assertTrue($report['has_errors']);
    $this->assertTrue($report['has_warnings']);

    $items = $report['items'];
    $this->assertNotEmpty($items);
    // Errors are sorted first.
    $this->assertSame('error', $items[0]['severity']);
    $this->assertSame('warning', $items[1]['severity']);

    // Values/descriptions are stripped of tags.
    $this->assertSame('hello', $items[0]['value']);
    $this->assertSame('desc', $items[0]['description']);

    $errorsOnly = $service->getRequirements(TRUE);
    $this->assertSame(2, count($errorsOnly['items']));
  }

  public function testGetDatabaseStatusIncludesTableCountWhenAvailable(): void {
    $schema = $this->createMock(Schema::class);
    $schema->method('findTables')->with('%')->willReturn(['a' => 'a', 'b' => 'b']);

    $connection = $this->createMock(Connection::class);
    $connection->method('driver')->willReturn('sqlite');
    $connection->method('version')->willReturn('3.42');
    $connection->method('getConnectionOptions')->willReturn([
      'database' => 'db',
      'host' => 'localhost',
      'prefix' => '',
    ]);
    $connection->method('schema')->willReturn($schema);

    $service = new SystemStatusService(
      $this->createMock(ModuleHandlerInterface::class),
      $connection,
    );

    $status = $service->getDatabaseStatus();
    $this->assertSame('sqlite', $status['driver']);
    $this->assertSame(2, $status['table_count']);
  }

}

}

namespace {

use Drupal\system\SystemManager;

/**
 * Implements hook_requirements() for SystemStatusService unit tests.
 *
 * @internal
 */
function mcp_tools_test_requirements_mod_requirements(string $phase): array {
  if ($phase !== 'runtime') {
    return [];
  }

  return [
    'mcp_tools_error' => [
      'title' => 'Error',
      'value' => '<b>hello</b>',
      'description' => '<i>desc</i>',
      'severity' => SystemManager::REQUIREMENT_ERROR,
    ],
    'mcp_tools_warning' => [
      'title' => 'Warning',
      'value' => 'warn',
      'severity' => SystemManager::REQUIREMENT_WARNING,
    ],
    'mcp_tools_ok' => [
      'title' => 'Ok',
      'value' => 'ok',
      'severity' => SystemManager::REQUIREMENT_OK,
    ],
    'mcp_tools_info' => [
      'title' => 'Info',
      'value' => 'info',
      // REQUIREMENT_INFO is a global constant defined in install.inc; use -1 fallback.
      'severity' => defined('REQUIREMENT_INFO') ? REQUIREMENT_INFO : -1,
    ],
  ];
}

}
