<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheCollector;
use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Tracks whether an MCP tool call is currently executing.
 *
 * This is used to scope side effects (like config-change tracking) to MCP
 * invocations only, without affecting normal Drupal admin actions.
 *
 * It also drops what this process remembers at the start of each tool call.
 * The STDIO server is one PHP process for a whole client session, and
 * Drupal keeps State, config, loaded entities, field lists, permissions and
 * cache tag counts in memory for the life of a process. Without the reset
 * in enter(), a call would answer from whatever the process read first,
 * however long ago.
 *
 * It is not a new request: Drupal remembers more than this resets. What
 * bundles exist and which workflow covers them, the languages and anything
 * a newly installed module adds stay as first read, until the server is
 * started again.
 */
class McpToolCallContext {

  /**
   * The depth.
   *
   * @var int
   */
  private int $depth = 0;

  /**
   * The correlation id.
   *
   * @var string|null
   */
  private ?string $correlationId = NULL;

  /**
   * Whether the client's user said yes to the call that is running.
   *
   * @var bool
   */
  private bool $confirmedByUser = FALSE;

  /**
   * Constructs the context.
   *
   * @param \Drupal\Core\State\StateInterface|null $state
   *   The state service, flushed and reset at the start of each tool call.
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $configFactory
   *   The config factory, whose in-memory config objects are dropped.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface|null $entityMemoryCache
   *   The in-memory entity cache, emptied.
   * @param \Drupal\Core\Cache\CacheTagsChecksumInterface|null $cacheTagsChecksum
   *   The cache tag checksum service, whose remembered counts are dropped.
   * @param \Drupal\Core\Cache\CacheBackendInterface|null $accessPolicyMemoryCache
   *   Core's per-process cache of the permissions worked out for accounts.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface|null $entityFieldManager
   *   The field manager, whose in-memory field lists are dropped.
   */
  public function __construct(
    private readonly ?StateInterface $state = NULL,
    private readonly ?ConfigFactoryInterface $configFactory = NULL,
    private readonly ?MemoryCacheInterface $entityMemoryCache = NULL,
    private readonly ?CacheTagsChecksumInterface $cacheTagsChecksum = NULL,
    private readonly ?CacheBackendInterface $accessPolicyMemoryCache = NULL,
    private readonly ?EntityFieldManagerInterface $entityFieldManager = NULL,
  ) {}

  /**
   * Mark the beginning of a tool call.
   */
  public function enter(): void {
    if ($this->depth === 0) {
      $this->correlationId = $this->generateCorrelationId();
      $this->confirmedByUser = FALSE;
      // State loads its values once per object. In the long-running stdio
      // server that would serve every call the values of the first read, so
      // each call starts like a new request: destruct() writes what the
      // last call left pending, as the end of a request does, and reset()
      // forgets this process's copy (Drupal 10's destruct() does not).
      // resetCache() would also delete the cache entry all requests share.
      if ($this->state instanceof CacheCollector) {
        $this->state->destruct();
        $this->state->reset();
      }
      else {
        $this->state?->resetCache();
      }
      // Config objects and loaded entities are kept per process, and cache
      // items are trusted against tag counts remembered per process. Other
      // processes change all three behind this one's back. The caches all
      // requests share stay as they are; only this process's own copies go.
      $this->configFactory?->reset();
      $this->entityMemoryCache?->deleteAll();
      $this->cacheTagsChecksum?->reset();
      // Core also keeps the permissions it worked out for each account in a
      // memory cache of its own. Without this a permission removed from a
      // role by another process stayed in force for the life of the server.
      $this->accessPolicyMemoryCache?->deleteAll();
      // The field lists per bundle are kept per process as well. A field
      // another process added was unknown here, and its value was dropped
      // when content was saved. Switching the caches off empties the lists
      // in memory and leaves the cache all requests share alone.
      $this->entityFieldManager?->useCaches(FALSE);
      $this->entityFieldManager?->useCaches(TRUE);
    }
    $this->depth++;
  }

  /**
   * Mark the end of a tool call.
   */
  public function leave(): void {
    if ($this->depth > 0) {
      $this->depth--;
    }
    if ($this->depth === 0) {
      $this->correlationId = NULL;
      $this->confirmedByUser = FALSE;
    }
  }

  /**
   * Notes that the client's user was asked about this call and said yes.
   *
   * The audit lines of the call say so. It holds until the call ends.
   */
  public function markConfirmedByUser(): void {
    $this->confirmedByUser = TRUE;
  }

  /**
   * Whether the client's user said yes to the call that is running.
   */
  public function wasConfirmedByUser(): bool {
    return $this->confirmedByUser;
  }

  /**
   * Returns whether a tool call is currently executing.
   */
  public function isActive(): bool {
    return $this->depth > 0;
  }

  /**
   * Returns a correlation ID for the current tool call (if active).
   */
  public function getCorrelationId(): ?string {
    return $this->correlationId;
  }

  /**
   * Generate a correlation ID for log grouping.
   */
  private function generateCorrelationId(): string {
    try {
      return bin2hex(random_bytes(8));
    }
    catch (\Throwable) {
      return substr(hash('sha256', uniqid('', TRUE)), 0, 16);
    }
  }

}
