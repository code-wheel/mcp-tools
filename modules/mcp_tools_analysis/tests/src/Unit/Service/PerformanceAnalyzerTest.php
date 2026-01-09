<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\mcp_tools_analysis\Service\PerformanceAnalyzer;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for PerformanceAnalyzer.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_analysis\Service\PerformanceAnalyzer::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_analysis')]
final class PerformanceAnalyzerTest extends UnitTestCase {

  private Connection $database;
  private ConfigFactoryInterface $configFactory;
  private ModuleHandlerInterface $moduleHandler;
  private PerformanceAnalyzer $analyzer;

  protected function setUp(): void {
    parent::setUp();
    $this->database = $this->createMock(Connection::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);

    $this->analyzer = new PerformanceAnalyzer(
      $this->database,
      $this->configFactory,
      $this->moduleHandler,
    );
  }

  public function testAnalyzePerformanceReturnsCacheSettings(): void {
    $performanceConfig = $this->createMock(ImmutableConfig::class);
    $performanceConfig->method('get')->willReturnMap([
      ['cache.page.max_age', 3600],
      ['css.preprocess', TRUE],
      ['js.preprocess', TRUE],
    ]);
    $this->configFactory->method('get')->with('system.performance')->willReturn($performanceConfig);
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    // Mock database query for table sizes.
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([]);
    $this->database->method('query')->willReturn($statement);

    $result = $this->analyzer->analyzePerformance();

    $this->assertTrue($result['success']);
    $this->assertSame(3600, $result['data']['cache_status']['page_cache_max_age']);
    $this->assertTrue($result['data']['cache_status']['css_aggregation']);
    $this->assertTrue($result['data']['cache_status']['js_aggregation']);
  }

  public function testAnalyzePerformanceWithDblog(): void {
    $performanceConfig = $this->createMock(ImmutableConfig::class);
    $performanceConfig->method('get')->willReturn(NULL);
    $this->configFactory->method('get')->willReturn($performanceConfig);
    $this->moduleHandler->method('moduleExists')->with('dblog')->willReturn(TRUE);

    // Mock select query for watchdog errors.
    $selectQuery = $this->createMock(SelectInterface::class);
    $selectQuery->method('fields')->willReturnSelf();
    $selectQuery->method('condition')->willReturnSelf();
    $selectQuery->method('orderBy')->willReturnSelf();
    $selectQuery->method('range')->willReturnSelf();

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([]);
    $selectQuery->method('execute')->willReturn($statement);

    $this->database->method('select')->willReturn($selectQuery);

    // Mock database query for table sizes.
    $sizeStatement = $this->createMock(StatementInterface::class);
    $sizeStatement->method('fetchAll')->willReturn([
      (object) ['table_name' => 'node', 'size_mb' => 10.5],
      (object) ['table_name' => 'watchdog', 'size_mb' => 5.2],
    ]);
    $this->database->method('query')->willReturn($sizeStatement);

    $result = $this->analyzer->analyzePerformance();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('watchdog_errors', $result['data']);
    $this->assertArrayHasKey('database', $result['data']);
    $this->assertCount(2, $result['data']['database']['largest_tables']);
  }

  public function testAnalyzePerformanceGeneratesSuggestions(): void {
    // Cache disabled config.
    $performanceConfig = $this->createMock(ImmutableConfig::class);
    $performanceConfig->method('get')->willReturnMap([
      ['cache.page.max_age', 0],
      ['css.preprocess', FALSE],
      ['js.preprocess', FALSE],
    ]);
    $this->configFactory->method('get')->willReturn($performanceConfig);
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([]);
    $this->database->method('query')->willReturn($statement);

    $result = $this->analyzer->analyzePerformance();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('suggestions', $result['data']);
  }

}
