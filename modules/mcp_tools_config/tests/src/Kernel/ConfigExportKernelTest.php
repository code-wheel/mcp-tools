<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_config\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * The export is what drush config:export writes.
 *
 * The raw active storage was copied: the site's config_exclude_modules
 * (and config_split, config_ignore) were not applied, and translations
 * (language collections) were never written.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_config')]
final class ConfigExportKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_config'];

  /**
   * An excluded module's config stays out of the sync directory.
   */
  public function testTheSitesExportRulesHold(): void {
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'user', 'dblog', 'mcp_tools']);
    $this->setSetting('config_exclude_modules', ['dblog']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write', 'admin']);
    $this->config('system.site')->set('name', 'Exported')->save();

    $result = $this->container->get('mcp_tools_config.config_management')->exportConfig();
    $this->assertTrue($result['success'], json_encode($result));
    $sync = $this->container->get('config.storage.sync');
    $this->assertSame('Exported', $sync->read('system.site')['name'] ?? NULL);
    $this->assertFalse($sync->exists('dblog.settings'), 'The config of an excluded module was exported.');
  }

}
