<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * The config status compares the site with its sync directory for real.
 *
 * The unit test for this was a placeholder that skipped itself, so the
 * comparison ran in no test.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ConfigStatusKernelTest extends KernelTestBase {

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
  ];

  /**
   * Reports nothing when both match, then the one config that differs.
   */
  public function testStatusListsWhatDiffersFromTheSyncDirectory(): void {
    $this->installConfig(['system', 'mcp_tools']);
    $service = $this->container->get('mcp_tools.config_analysis');
    $sync = $this->container->get('config.storage.sync');

    $this->assertFalse($service->getConfigStatus()['sync_directory_exists'], 'The sync directory starts empty.');

    $this->copyConfig($this->container->get('config.storage'), $sync);
    $status = $service->getConfigStatus();
    $this->assertArrayNotHasKey('error', $status);
    $this->assertTrue($status['sync_directory_exists']);
    $this->assertFalse($status['has_changes']);
    $this->assertSame(0, $status['total_changes']);
    $this->assertSame([], $status['changes']);

    $this->config('system.site')->set('name', 'Changed')->save();
    $status = $service->getConfigStatus();
    $this->assertTrue($status['has_changes']);
    $this->assertSame(1, $status['total_changes']);
    $this->assertSame('system.site', $status['changes'][0]['name']);
    $this->assertSame('update', $status['changes'][0]['operation']);
  }

}
