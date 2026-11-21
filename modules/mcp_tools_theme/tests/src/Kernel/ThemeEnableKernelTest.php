<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_theme\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Enabling and disabling a theme, on real services.
 *
 * Both called install() and uninstall() on the theme handler. Those moved
 * to the theme installer in Drupal 8 and are gone, so both tools failed
 * with "Call to undefined method" every time. The unit test only covered
 * the refusals.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_theme')]
final class ThemeEnableKernelTest extends KernelTestBase {

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
    'mcp_tools_theme',
  ];

  /**
   * A theme can be enabled and disabled again.
   */
  public function testEnableAndDisable(): void {
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'mcp_tools']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $service = $this->container->get('mcp_tools_theme.theme');

    $result = $service->enableTheme('claro');
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertTrue($result['data']['changed']);
    $this->assertTrue($this->container->get('theme_handler')->themeExists('claro'));

    $result = $service->disableTheme('claro');
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertFalse($this->container->get('theme_handler')->themeExists('claro'));
  }

}
