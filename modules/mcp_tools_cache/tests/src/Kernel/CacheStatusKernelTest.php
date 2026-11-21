<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_cache\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Cache status lists the site's cache bins, on a real container.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_cache')]
final class CacheStatusKernelTest extends KernelTestBase {

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
    'mcp_tools_cache',
  ];

  /**
   * Only cache bins are listed, not every service that starts with "cache.".
   *
   * The list was built from service names, so cache backend factories such
   * as cache.backend.memory showed up as bins called "backend.memory". On
   * SQLite the size check of such a "bin" failed and took the tool with it.
   */
  public function testStatusListsOnlyCacheBins(): void {
    $status = $this->container->get('mcp_tools_cache.cache_service')->getCacheStatus();

    $this->assertTrue($status['success']);
    $listed = array_column($status['data']['bins'], 'bin');
    $this->assertSame(count($listed), $status['data']['total_bins']);

    foreach ($listed as $bin) {
      $this->assertStringStartsNotWith('backend.', $bin);
    }

    // Every bin the container knows is there.
    foreach ($this->container->getParameter('cache_bins') as $bin) {
      $this->assertContains($bin, $listed);
    }
  }

}
