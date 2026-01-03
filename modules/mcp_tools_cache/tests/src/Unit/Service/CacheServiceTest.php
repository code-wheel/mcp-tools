<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_cache\Service;

/**
 * Test stub for drupal_flush_all_caches().
 *
 * @internal
 */
if (!function_exists(__NAMESPACE__ . '\drupal_flush_all_caches')) {
  function drupal_flush_all_caches(): void {
    $GLOBALS['mcp_tools_cache_test_flushed'] = TRUE;
  }
}

/**
 * Test stub for _drupal_flush_css_js().
 *
 * @internal
 */
if (!function_exists(__NAMESPACE__ . '\_drupal_flush_css_js')) {
  function _drupal_flush_css_js(): void {
    $GLOBALS['mcp_tools_cache_test_css_js_flushed'] = TRUE;
  }
}

namespace Drupal\Tests\mcp_tools_cache\Unit\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\mcp_tools_cache\Service\CacheService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\mcp_tools_cache\Service\CacheService
 * @group mcp_tools_cache
 */
final class CacheServiceTest extends UnitTestCase {

  private function withDrupalContainer(ContainerBuilder $container, callable $callback): void {
    $previous = NULL;
    try {
      $previous = \Drupal::getContainer();
    }
    catch (\Throwable) {
      $previous = NULL;
    }

    \Drupal::setContainer($container);
    try {
      $callback();
    }
    finally {
      if ($previous) {
        \Drupal::setContainer($previous);
      }
      elseif (method_exists(\Drupal::class, 'unsetContainer')) {
        \Drupal::unsetContainer();
      }
    }
  }

  private function createService(Connection $database): CacheService {
    return new CacheService(
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
      $database,
    );
  }

  /**
   * @covers ::clearAllCaches
   */
  public function testClearAllCachesUsesFlushFunction(): void {
    unset($GLOBALS['mcp_tools_cache_test_flushed']);

    $database = $this->createMock(Connection::class);
    $service = $this->createService($database);

    $result = $service->clearAllCaches();
    $this->assertTrue($result['success']);
    $this->assertTrue((bool) ($GLOBALS['mcp_tools_cache_test_flushed'] ?? FALSE));
  }

  /**
   * @covers ::clearCacheBin
   * @covers ::getAvailableBins
   */
  public function testClearCacheBinValidatesKnownBins(): void {
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturn(FALSE);

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);

    $service = $this->createService($database);

    $container = new ContainerBuilder();
    $container->set('cache.default', new class() {
      public bool $deleted = FALSE;
      public function deleteAll(): void { $this->deleted = TRUE; }
    });

    $this->withDrupalContainer($container, function () use ($service): void {
      $unknown = $service->clearCacheBin('not_a_bin');
      $this->assertFalse($unknown['success']);
      $this->assertSame('NOT_FOUND', $unknown['code']);

      $ok = $service->clearCacheBin('default');
      $this->assertTrue($ok['success']);
      $this->assertSame('default', $ok['bin']);
    });
  }

  /**
   * @covers ::getCacheStatus
   * @covers ::getCacheBackend
   * @covers ::estimateBinSize
   * @covers ::getCacheTagsTableSize
   */
  public function testGetCacheStatusIncludesCustomBinsAndSizes(): void {
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturnCallback(static function (string $table): bool {
      return in_array($table, ['cache_default', 'cachetags'], TRUE);
    });

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')->willReturnCallback(static function (string $table): object {
      $count = $table === 'cachetags' ? 7 : 12;
      return new class($count) {
        public function __construct(private readonly int $count) {}
        public function countQuery(): static { return $this; }
        public function execute(): object {
          return new class($this->count) {
            public function __construct(private readonly int $count) {}
            public function fetchField(): int { return $this->count; }
          };
        }
      };
    });

    $service = $this->createService($database);

    $container = new ContainerBuilder();
    $container->set('cache.backend.database', new class() {});
    $container->set('cache.default', new class() {});
    $container->set('cache.custom', new class() {});

    $this->withDrupalContainer($container, function () use ($service): void {
      $status = $service->getCacheStatus();
      $this->assertGreaterThanOrEqual(10, $status['total_bins']);
      $this->assertSame(7, $status['cache_tags_table_size']);
    });
  }

  /**
   * @covers ::rebuild
   */
  public function testRebuildValidatesTypeAndInvokesServices(): void {
    unset($GLOBALS['mcp_tools_cache_test_css_js_flushed']);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturn(FALSE);

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);

    $service = $this->createService($database);

    $container = new ContainerBuilder();
    $routerBuilder = new class() { public bool $called = FALSE; public function rebuild(): void { $this->called = TRUE; } };
    $container->set('router.builder', $routerBuilder);
    $container->set('theme.registry', new class() { public function reset(): void {} });
    $container->set('asset.css.collection_optimizer', new class() { public function deleteAll(): void {} });
    $container->set('asset.js.collection_optimizer', new class() { public function deleteAll(): void {} });
    $container->set('kernel', new class() { public function rebuildContainer(): void {} });
    $container->set('plugin.manager.menu.link', new class() { public function rebuild(): void {} });
    $container->set('cache.default', new class() {});

    $this->withDrupalContainer($container, function () use ($service, $routerBuilder): void {
      $invalid = $service->rebuild('nope');
      $this->assertFalse($invalid['success']);
      $this->assertSame('VALIDATION_ERROR', $invalid['code']);

      $result = $service->rebuild('router');
      $this->assertTrue($result['success']);
      $this->assertTrue($routerBuilder->called);

      $theme = $service->rebuild('theme');
      $this->assertTrue($theme['success']);
      $this->assertTrue((bool) ($GLOBALS['mcp_tools_cache_test_css_js_flushed'] ?? FALSE));
    });
  }

}
