<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_cron\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * The cron interval goes where Drupal reads it.
 *
 * The tool wrote system.cron:threshold.autorun, a Drupal 7 key nothing
 * reads, and said "updated": cron ran as before.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_cron')]
final class CronIntervalKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'automated_cron', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_cron'];

  /**
   * The interval is Automated Cron's, and without it the call is refused.
   */
  public function testTheIntervalIsAutomatedCrons(): void {
    $this->installSchema('dblog', ['watchdog']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'automated_cron', 'mcp_tools']);
    $service = $this->container->get('mcp_tools_cron.cron_service');
    $this->assertTrue($service->updateSettings(3600)['success']);
    $this->assertSame(3600, (int) $this->config('automated_cron.settings')->get('interval'), 'The interval Drupal runs cron by did not change.');
    $this->assertNull($this->config('system.cron')->get('threshold.autorun'), 'The Drupal 7 key was written.');
    $this->assertSame(3600, $service->getCronStatus()['data']['autorun_threshold']);

    // Under Drush (the STDIO server) the interval reads 0: Drush overrides
    // it so cron does not run in its requests. The status says the stored
    // interval, the one web requests run cron by.
    $GLOBALS['config']['automated_cron.settings']['interval'] = 0;
    $this->container->get('config.factory')->reset('automated_cron.settings');
    try {
      $this->assertSame(3600, $service->getCronStatus()['data']['autorun_threshold'], 'Under Drush the status said Automated Cron never runs.');
    }
    finally {
      unset($GLOBALS['config']['automated_cron.settings']);
      $this->container->get('config.factory')->reset('automated_cron.settings');
    }

    $this->container->get('module_installer')->uninstall(['automated_cron']);
    $service = $this->container->get('mcp_tools_cron.cron_service');
    $refused = $service->updateSettings(60);
    $this->assertFalse($refused['success'], 'A site without Automated Cron was told the interval changed.');
    $this->assertSame('MODULE_NOT_ENABLED', $refused['code']);
  }

}
