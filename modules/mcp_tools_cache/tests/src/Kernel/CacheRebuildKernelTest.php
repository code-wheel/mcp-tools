<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_cache\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Every rebuild type works on a real container.
 *
 * The unit test replaced a core function with a stand-in, so it could not
 * see that Drupal 11 no longer has that function.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_cache')]
final class CacheRebuildKernelTest extends KernelTestBase {

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
   * Each type the tool offers is rebuilt without an error.
   */
  public function testEveryRebuildTypeWorks(): void {
    foreach (['router', 'theme', 'menu', 'container'] as $type) {
      // A container rebuild replaces the services, so ask for it each time.
      $result = \Drupal::service('mcp_tools_cache.cache_service')->rebuild($type);
      $this->assertTrue($result['success'], $type . ': ' . ($result['error'] ?? ''));
      $this->assertSame($type, $result['type']);
    }
  }

  /**
   * A theme rebuild changes the query string browsers get for CSS and JS.
   */
  public function testThemeRebuildResetsTheAssetQueryString(): void {
    $state = $this->container->get('state');
    $state->delete('asset.css_js_query_string');

    $result = $this->container->get('mcp_tools_cache.cache_service')->rebuild('theme');

    $this->assertTrue($result['success'], $result['error'] ?? '');
    $this->assertNotEmpty($state->get('asset.css_js_query_string'));
  }

}
