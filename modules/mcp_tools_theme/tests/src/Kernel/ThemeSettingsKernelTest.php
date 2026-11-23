<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_theme\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Theme settings go to an installed theme, and fit its schema.
 *
 * Settings of an uninstalled theme were saved as its config, which then
 * stopped the theme from being installed; any key or type was stored.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_theme')]
final class ThemeSettingsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_theme'];

  /**
   * An uninstalled theme is refused, a key it has not too.
   */
  public function testSettingsOfAThemeThatIsThere(): void {
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'mcp_tools']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write', 'admin']);
    $service = $this->container->get('mcp_tools_theme.theme');

    $refused = $service->updateThemeSettings('olivero', ['features' => ['node_user_picture' => FALSE]]);
    $this->assertFalse($refused['success'], 'Settings were saved for a theme that is not installed.');
    $this->assertTrue($this->config('olivero.settings')->isNew(), 'Config of an uninstalled theme was made.');

    $this->container->get('theme_installer')->install(['stark']);
    // Installing a theme rebuilds the container, and the scopes with it.
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write', 'admin']);
    $service = $this->container->get('mcp_tools_theme.theme');
    $ok = $service->updateThemeSettings('stark', ['features' => ['node_user_picture' => FALSE]]);
    $this->assertTrue($ok['success'], (string) ($ok['error'] ?? ''));
    $this->assertFalse($service->updateThemeSettings('stark', ['no_such_setting' => 1])['success'], 'A key the theme settings do not have was stored.');
  }

}
