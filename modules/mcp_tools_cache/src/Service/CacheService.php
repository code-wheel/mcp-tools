<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_cache\Service;

use CodeWheel\McpErrorCodes\ErrorCode;
use Drupal\Core\Asset\AssetCollectionOptimizerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Theme\Registry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service for cache management operations.
 */
class CacheService {

  /**
   * Known cache bins in Drupal core.
   */
  protected const CORE_BINS = [
    'bootstrap',
    'config',
    'data',
    'default',
    'discovery',
    'dynamic_page_cache',
    'entity',
    'menu',
    'render',
    'page',
  ];

  public function __construct(
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    protected ModuleHandlerInterface $moduleHandler,
    protected Connection $database,
    protected CacheFactoryInterface $cacheFactory,
    protected RouteBuilderInterface $routerBuilder,
    protected Registry $themeRegistry,
    protected AssetCollectionOptimizerInterface $cssOptimizer,
    protected AssetCollectionOptimizerInterface $jsOptimizer,
    protected DrupalKernelInterface $kernel,
    protected MenuLinkManagerInterface $menuLinkManager,
    protected ContainerInterface $container,
  ) {}

  /**
   * Get cache status overview.
   *
   * @return array
   *   Cache status information.
   */
  public function getCacheStatus(): array {
    $bins = $this->getAvailableBins();
    $status = [];

    foreach ($bins as $bin) {
      $status[] = [
        'bin' => $bin,
        'backend' => $this->getCacheBackend($bin),
        'estimated_items' => $this->estimateBinSize($bin),
      ];
    }

    return [
      'success' => TRUE,
      'data' => [
        'total_bins' => count($bins),
        'bins' => $status,
        'cache_tags_table_size' => $this->getCacheTagsTableSize(),
      ],
    ];
  }

  /**
   * Clear all caches.
   *
   * @return array
   *   Result of the operation.
   */
  public function clearAllCaches(): array {
    drupal_flush_all_caches();

    return [
      'success' => TRUE,
      'message' => 'All caches have been cleared.',
      'cleared_at' => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Clear a specific cache bin.
   *
   * @param string $bin
   *   The cache bin name.
   *
   * @return array
   *   Result of the operation.
   */
  public function clearCacheBin(string $bin): array {
    $bins = $this->getAvailableBins();

    if (!in_array($bin, $bins)) {
      return [
        'success' => FALSE,
        'error' => "Unknown cache bin '$bin'.",
        'code' => ErrorCode::NOT_FOUND,
        'available_bins' => $bins,
      ];
    }

    try {
      $cache = $this->cacheFactory->get($bin);
      $cache->deleteAll();

      return [
        'success' => TRUE,
        'message' => "Cache bin '$bin' has been cleared.",
        'bin' => $bin,
        'cleared_at' => date('Y-m-d H:i:s'),
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => "Failed to clear cache bin '$bin': " . $e->getMessage(),
        'code' => ErrorCode::INTERNAL_ERROR,
      ];
    }
  }

  /**
   * Invalidate cache tags.
   *
   * @param array $tags
   *   Array of cache tags to invalidate.
   *
   * @return array
   *   Result of the operation.
   */
  public function invalidateTags(array $tags): array {
    if (empty($tags)) {
      return [
        'success' => FALSE,
        'error' => 'At least one cache tag is required.',
        'code' => ErrorCode::VALIDATION_ERROR,
      ];
    }

    $this->cacheTagsInvalidator->invalidateTags($tags);

    return [
      'success' => TRUE,
      'message' => 'Cache tags invalidated successfully.',
      'tags' => $tags,
      'invalidated_at' => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Clear render cache for a specific entity.
   *
   * @param string $entityType
   *   The entity type.
   * @param string|int $entityId
   *   The entity ID.
   *
   * @return array
   *   Result of the operation.
   */
  public function clearEntityCache(string $entityType, string|int $entityId): array {
    $tags = [
      "{$entityType}:{$entityId}",
      "{$entityType}_list",
    ];

    $this->cacheTagsInvalidator->invalidateTags($tags);

    return [
      'success' => TRUE,
      'message' => "Cache cleared for $entityType $entityId.",
      'invalidated_tags' => $tags,
    ];
  }

  /**
   * Rebuild specific caches.
   *
   * @param string $type
   *   Type of rebuild: 'router', 'theme', 'container', 'menu'.
   *
   * @return array
   *   Result of the operation.
   */
  public function rebuild(string $type): array {
    $validTypes = ['router', 'theme', 'container', 'menu'];

    if (!in_array($type, $validTypes)) {
      return [
        'success' => FALSE,
        'error' => "Unknown rebuild type '$type'.",
        'code' => ErrorCode::VALIDATION_ERROR,
        'valid_types' => $validTypes,
      ];
    }

    try {
      switch ($type) {
        case 'router':
          $this->routerBuilder->rebuild();
          break;

        case 'theme':
          $this->themeRegistry->reset();
          $this->cssOptimizer->deleteAll();
          $this->jsOptimizer->deleteAll();
          _drupal_flush_css_js();
          break;

        case 'container':
          $this->kernel->rebuildContainer();
          break;

        case 'menu':
          $this->menuLinkManager->rebuild();
          break;
      }

      return [
        'success' => TRUE,
        'message' => "Successfully rebuilt $type cache.",
        'type' => $type,
        'rebuilt_at' => date('Y-m-d H:i:s'),
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => "Failed to rebuild $type: " . $e->getMessage(),
        'code' => ErrorCode::INTERNAL_ERROR,
      ];
    }
  }

  /**
   * Get available cache bins.
   *
   * @return array
   *   List of cache bin names.
   */
  protected function getAvailableBins(): array {
    $bins = self::CORE_BINS;

    // Add any additional bins from contrib modules.
    foreach ($this->container->getServiceIds() as $id) {
      if (str_starts_with($id, 'cache.') && $id !== 'cache.backend.database') {
        $bin = substr($id, 6);
        if (!in_array($bin, $bins)) {
          $bins[] = $bin;
        }
      }
    }

    sort($bins);
    return $bins;
  }

  /**
   * Get the cache backend for a bin.
   *
   * @param string $bin
   *   Cache bin name.
   *
   * @return string
   *   Backend type.
   */
  protected function getCacheBackend(string $bin): string {
    try {
      $cache = $this->cacheFactory->get($bin);
      $class = get_class($cache);
      return match (TRUE) {
        str_contains($class, 'Database') => 'database',
        str_contains($class, 'Memcache') => 'memcache',
        str_contains($class, 'Redis') => 'redis',
        str_contains($class, 'Apcu') => 'apcu',
        str_contains($class, 'Null') => 'null',
        str_contains($class, 'ChainedFast') => 'chained_fast',
        default => $class,
      };
    }
    catch (\Exception $e) {
      return 'unknown';
    }
  }

  /**
   * Estimate the size of a cache bin.
   *
   * @param string $bin
   *   Cache bin name.
   *
   * @return int|null
   *   Estimated item count or null if not available.
   */
  protected function estimateBinSize(string $bin): ?int {
    $tableName = 'cache_' . $bin;

    if (!$this->database->schema()->tableExists($tableName)) {
      return NULL;
    }

    try {
      return (int) $this->database->select($tableName)
        ->countQuery()
        ->execute()
        ->fetchField();
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Get cache tags table size.
   *
   * @return int|null
   *   Number of cache tag entries.
   */
  protected function getCacheTagsTableSize(): ?int {
    if (!$this->database->schema()->tableExists('cachetags')) {
      return NULL;
    }

    try {
      return (int) $this->database->select('cachetags')
        ->countQuery()
        ->execute()
        ->fetchField();
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

}
