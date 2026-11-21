<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * The performance analysis runs on the site's own database.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_analysis')]
final class PerformanceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_analysis',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system']);
  }

  /**
   * The analysis works on every database Drupal supports.
   *
   * The table size query is MySQL's. On SQLite and PostgreSQL it raised an
   * SQL error and the whole analysis failed.
   */
  public function testAnalysisRunsOnTheSiteDatabase(): void {
    $result = $this->container->get('mcp_tools_analysis.performance_analyzer')->analyzePerformance();

    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertIsArray($result['data']['database']['largest_tables']);
    $this->assertArrayHasKey('page_cache_max_age', $result['data']['cache_status']);

    if ($this->container->get('database')->databaseType() !== 'mysql') {
      $this->assertSame([], $result['data']['database']['largest_tables']);
      $this->assertNotEmpty($result['data']['database']['note']);
      return;
    }

    // MySQL 8 names the columns of information_schema in capitals, MariaDB
    // does not. Read without an alias, every table name came back empty on
    // MySQL 8, with a PHP warning for each row.
    $this->assertNotEmpty($result['data']['database']['largest_tables']);
    foreach ($result['data']['database']['largest_tables'] as $row) {
      $this->assertIsString($row['table']);
      $this->assertNotSame('', $row['table']);
      $this->assertIsFloat($row['size_mb']);
    }
  }

}
