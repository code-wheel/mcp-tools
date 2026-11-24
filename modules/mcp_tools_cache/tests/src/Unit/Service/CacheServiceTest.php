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

namespace Drupal\Tests\mcp_tools_cache\Unit\Service;

use Drupal\Core\Asset\AssetCollectionOptimizerInterface;
use Drupal\Core\Asset\AssetQueryStringInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\DrupalKernelInterface;
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
    // A container stand-in. Core lists the cache bin services in the
    // "cache_bins" parameter (service name => bin name).
    $this->container = new class implements ContainerInterface {
      public array $serviceIds = [];

      public array $parameters = [];

      public array $services = [];

      public function getServiceIds(): array {
        return $this->serviceIds;
      }

      public function set(string $id, ?object $service): void {}

      public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object {
        return $this->services[$id] ?? NULL;
      }

      public function has(string $id): bool {
        return FALSE;
      }

      public function initialized(string $id): bool {
        return FALSE;
      }

      public function getParameter(string $name): \UnitEnum|array|string|int|float|bool|null {
        return $this->parameters[$name] ?? NULL;
      }

      public function hasParameter(string $name): bool {
        return isset($this->parameters[$name]);
      }

      public function setParameter(string $name, mixed $value): void {}
    };
  }

  private function createService(Connection $database): CacheService {
    return new CacheService(
      $this->createMock(CacheTagsInvalidatorInterface::class),
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
    $this->container->serviceIds = [
      'cache.default',
      'cache.backend.database',
    ];

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

    // The container as core builds it: bins in the "cache_bins" parameter,
    // next to services that start with "cache." but are not bins.
    $this->container->serviceIds = [
      'cache.default',
      'cache.custom',
      'cache.backend.database',
      'cache.backend.memory',
    ];
    $this->container->parameters['cache_bins'] = [
      'cache.default' => 'default',
      'cache.custom' => 'custom',
    ];

    // Configure cache factory.
    $cacheBackend = $this->createMock(CacheBackendInterface::class);
    $this->cacheFactory->method('get')->willReturn($cacheBackend);

    $service = $this->createService($database);

    $status = $service->getCacheStatus();
    $this->assertTrue($status['success']);
    // The ten core bins plus the custom one, and no backend factories.
    $this->assertSame(11, $status['data']['total_bins']);
    $listed = array_column($status['data']['bins'], 'bin');
    $this->assertContains('custom', $listed);
    $this->assertNotContains('backend.memory', $listed);
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
    // The query string service is asked for, not a core function: Drupal 11
    // removed the function. CacheRebuildKernelTest runs the real thing.
    $queryString = $this->createMock(AssetQueryStringInterface::class);
    $queryString->expects($this->once())->method('reset');
    $this->container->services['asset.query_string'] = $queryString;

    $service = $this->createService($database);

    $invalid = $service->rebuild('nope');
    $this->assertFalse($invalid['success']);
    $this->assertSame('VALIDATION_ERROR', $invalid['code']);

    $result = $service->rebuild('router');
    $this->assertTrue($result['success']);

    $theme = $service->rebuild('theme');
    $this->assertTrue($theme['success']);
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
