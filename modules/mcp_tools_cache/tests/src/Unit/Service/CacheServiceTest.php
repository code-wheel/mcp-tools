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

use Drupal\Core\Asset\AssetCollectionOptimizerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Theme\Registry;
use Drupal\mcp_tools_cache\Service\CacheService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_cache\Service\CacheService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_cache')]
final class CacheServiceTest extends UnitTestCase {

  private CacheFactoryInterface $cacheFactory;
  private RouteBuilderInterface $routerBuilder;
  private Registry $themeRegistry;
  private AssetCollectionOptimizerInterface $cssOptimizer;
  private AssetCollectionOptimizerInterface $jsOptimizer;
  private DrupalKernelInterface $kernel;
  private MenuLinkManagerInterface $menuLinkManager;
  private ContainerInterface $container;

  protected function setUp(): void {
    parent::setUp();

    $this->cacheFactory = $this->createMock(CacheFactoryInterface::class);
    $this->routerBuilder = $this->createMock(RouteBuilderInterface::class);
    $this->themeRegistry = $this->createMock(Registry::class);
    $this->cssOptimizer = $this->createMock(AssetCollectionOptimizerInterface::class);
    $this->jsOptimizer = $this->createMock(AssetCollectionOptimizerInterface::class);
    $this->kernel = $this->createMock(DrupalKernelInterface::class);
    $this->menuLinkManager = $this->createMock(MenuLinkManagerInterface::class);
    $this->container = $this->createMock(ContainerInterface::class);
  }

  private function createService(Connection $database): CacheService {
    return new CacheService(
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
      $database,
      $this->cacheFactory,
      $this->routerBuilder,
      $this->themeRegistry,
      $this->cssOptimizer,
      $this->jsOptimizer,
      $this->kernel,
      $this->menuLinkManager,
      $this->container,
    );
  }

  public function testClearAllCachesUsesFlushFunction(): void {
    unset($GLOBALS['mcp_tools_cache_test_flushed']);

    $database = $this->createMock(Connection::class);
    $service = $this->createService($database);

    $result = $service->clearAllCaches();
    $this->assertTrue($result['success']);
    $this->assertTrue((bool) ($GLOBALS['mcp_tools_cache_test_flushed'] ?? FALSE));
  }

  public function testClearCacheBinValidatesKnownBins(): void {
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturn(FALSE);

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);

    // Configure container to return core bin service IDs.
    $this->container->method('getServiceIds')->willReturn([
      'cache.default',
      'cache.backend.database',
    ]);

    // Configure cache factory to return a mock cache backend.
    $cacheBackend = $this->createMock(CacheBackendInterface::class);
    $cacheBackend->expects($this->once())->method('deleteAll');
    $this->cacheFactory->method('get')->with('default')->willReturn($cacheBackend);

    $service = $this->createService($database);

    $unknown = $service->clearCacheBin('not_a_bin');
    $this->assertFalse($unknown['success']);
    $this->assertSame('NOT_FOUND', $unknown['code']);

    $ok = $service->clearCacheBin('default');
    $this->assertTrue($ok['success']);
    $this->assertSame('default', $ok['bin']);
  }

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

    // Configure container to return service IDs including custom cache bin.
    $this->container->method('getServiceIds')->willReturn([
      'cache.default',
      'cache.custom',
      'cache.backend.database',
    ]);

    // Configure cache factory.
    $cacheBackend = $this->createMock(CacheBackendInterface::class);
    $this->cacheFactory->method('get')->willReturn($cacheBackend);

    $service = $this->createService($database);

    $status = $service->getCacheStatus();
    $this->assertTrue($status['success']);
    $this->assertGreaterThanOrEqual(10, $status['data']['total_bins']);
    $this->assertSame(7, $status['data']['cache_tags_table_size']);
  }

  public function testRebuildValidatesTypeAndInvokesServices(): void {
    unset($GLOBALS['mcp_tools_cache_test_css_js_flushed']);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturn(FALSE);

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);

    // Expect router builder to be called.
    $this->routerBuilder->expects($this->once())->method('rebuild');

    // Expect theme rebuild services to be called.
    $this->themeRegistry->expects($this->once())->method('reset');
    $this->cssOptimizer->expects($this->once())->method('deleteAll');
    $this->jsOptimizer->expects($this->once())->method('deleteAll');

    $service = $this->createService($database);

    $invalid = $service->rebuild('nope');
    $this->assertFalse($invalid['success']);
    $this->assertSame('VALIDATION_ERROR', $invalid['code']);

    $result = $service->rebuild('router');
    $this->assertTrue($result['success']);

    $theme = $service->rebuild('theme');
    $this->assertTrue($theme['success']);
    $this->assertTrue((bool) ($GLOBALS['mcp_tools_cache_test_css_js_flushed'] ?? FALSE));
  }

  public function testInvalidateTagsRequiresAtLeastOneTag(): void {
    $database = $this->createMock(Connection::class);
    $service = $this->createService($database);

    $result = $service->invalidateTags([]);
    $this->assertFalse($result['success']);
    $this->assertSame('VALIDATION_ERROR', $result['code']);
  }

  public function testClearEntityCacheInvalidatesCorrectTags(): void {
    $cacheTagsInvalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with(['node:123', 'node_list']);

    $service = new CacheService(
      $cacheTagsInvalidator,
      $this->createMock(ModuleHandlerInterface::class),
      $this->createMock(Connection::class),
      $this->cacheFactory,
      $this->routerBuilder,
      $this->themeRegistry,
      $this->cssOptimizer,
      $this->jsOptimizer,
      $this->kernel,
      $this->menuLinkManager,
      $this->container,
    );

    $result = $service->clearEntityCache('node', 123);
    $this->assertTrue($result['success']);
    $this->assertSame(['node:123', 'node_list'], $result['invalidated_tags']);
  }

}
