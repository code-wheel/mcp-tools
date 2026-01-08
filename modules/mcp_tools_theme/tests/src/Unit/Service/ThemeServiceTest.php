<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_theme\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_theme\Service\ThemeService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for ThemeService.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_theme\Service\ThemeService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_theme')]
class ThemeServiceTest extends UnitTestCase {

  protected ThemeHandlerInterface $themeHandler;
  protected ThemeManagerInterface $themeManager;
  protected ConfigFactoryInterface $configFactory;
  protected ImmutableConfig $systemThemeConfig;
  protected ThemeExtensionList $themeExtensionList;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->themeHandler = $this->createMock(ThemeHandlerInterface::class);
    $this->themeManager = $this->createMock(ThemeManagerInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->themeExtensionList = $this->createMock(ThemeExtensionList::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);

    // Default config behavior.
    $this->systemThemeConfig = $this->createMock(ImmutableConfig::class);
    $this->systemThemeConfig->method('get')
      ->willReturnMap([
        ['default', 'olivero'],
        ['admin', 'claro'],
      ]);
    $this->configFactory->method('get')
      ->willReturnCallback(function (string $name): ImmutableConfig {
        return $name === 'system.theme'
          ? $this->systemThemeConfig
          : $this->createMock(ImmutableConfig::class);
      });
  }

  /**
   * Creates a ThemeService instance.
   */
  protected function createThemeService(): ThemeService {
    return new ThemeService(
      $this->themeHandler,
      $this->themeManager,
      $this->configFactory,
      $this->themeExtensionList,
      $this->accessManager,
      $this->auditLogger
    );
  }

  public function testGetActiveTheme(): void {
    $activeTheme = $this->createMock(ActiveTheme::class);
    $activeTheme->method('getName')->willReturn('olivero');
    $activeTheme->method('getPath')->willReturn('core/themes/olivero');
    $activeTheme->method('getEngine')->willReturn('twig');
    $activeTheme->method('getBaseThemeExtensions')->willReturn([]);
    $activeTheme->method('getRegions')->willReturn(['header', 'content', 'footer']);

    $this->themeManager->method('getActiveTheme')->willReturn($activeTheme);

    $service = $this->createThemeService();
    $result = $service->getActiveTheme();

    $this->assertTrue($result['success']);
    $this->assertEquals('olivero', $result['data']['active_theme']);
    $this->assertEquals('olivero', $result['data']['default_theme']);
    $this->assertEquals('claro', $result['data']['admin_theme']);
  }

  public function testListThemesInstalledOnly(): void {
    $oliveroTheme = $this->createMock(Extension::class);
    $oliveroTheme->info = [
      'name' => 'Olivero',
      'description' => 'A modern Drupal theme.',
      'version' => '10.2.0',
    ];
    $oliveroTheme->method('getPath')->willReturn('core/themes/olivero');

    $claroTheme = $this->createMock(Extension::class);
    $claroTheme->info = [
      'name' => 'Claro',
      'description' => 'Admin theme for Drupal.',
      'version' => '10.2.0',
    ];
    $claroTheme->method('getPath')->willReturn('core/themes/claro');

    $this->themeHandler->method('listInfo')->willReturn([
      'olivero' => $oliveroTheme,
      'claro' => $claroTheme,
    ]);

    $service = $this->createThemeService();
    $result = $service->listThemes(FALSE);

    $this->assertTrue($result['success']);
    $this->assertEquals(2, $result['data']['total']);
    $this->assertCount(2, $result['data']['themes']);
    $this->assertEquals('olivero', $result['data']['default_theme']);
    $this->assertEquals('claro', $result['data']['admin_theme']);
  }

  public function testSetDefaultThemeAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createThemeService();
    $result = $service->setDefaultTheme('bartik');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('denied', $result['error']);
  }

  public function testSetDefaultThemeNotInstalled(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->themeHandler->method('themeExists')->willReturn(FALSE);

    $service = $this->createThemeService();
    $result = $service->setDefaultTheme('nonexistent_theme');

    $this->assertFalse($result['success']);
  }

  public function testSetAdminThemeAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createThemeService();
    $result = $service->setAdminTheme('seven');

    $this->assertFalse($result['success']);
  }

  public function testEnableThemeAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createThemeService();
    $result = $service->enableTheme('bartik');

    $this->assertFalse($result['success']);
  }

  public function testDisableThemeAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createThemeService();
    $result = $service->disableTheme('bartik');

    $this->assertFalse($result['success']);
  }

  public function testDisableThemeCannotDisableDefault(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->themeHandler->method('themeExists')->willReturn(TRUE);
    $installedTheme = $this->createMock(Extension::class);
    $installedTheme->info = ['name' => 'Olivero'];
    $this->themeHandler->method('listInfo')->willReturn([
      'olivero' => $installedTheme,
    ]);

    $service = $this->createThemeService();
    $result = $service->disableTheme('olivero');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Cannot disable', $result['error']);
  }

  public function testDisableThemeCannotDisableAdmin(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->themeHandler->method('themeExists')->willReturn(TRUE);
    $installedTheme = $this->createMock(Extension::class);
    $installedTheme->info = ['name' => 'Claro'];
    $this->themeHandler->method('listInfo')->willReturn([
      'claro' => $installedTheme,
    ]);

    $service = $this->createThemeService();
    $result = $service->disableTheme('claro');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Cannot disable', $result['error']);
  }

  public function testGetThemeSettings(): void {
    $themeSettingsConfig = $this->createMock(ImmutableConfig::class);
    $themeSettingsConfig->method('getRawData')->willReturn([
      'logo' => ['path' => 'logo.svg'],
      'favicon' => ['path' => 'favicon.ico'],
    ]);

    $globalThemeConfig = $this->createMock(ImmutableConfig::class);
    $globalThemeConfig->method('getRawData')->willReturn([]);

    $this->configFactory->method('get')
      ->willReturnCallback(function (string $name) use ($themeSettingsConfig, $globalThemeConfig): ImmutableConfig {
        return match ($name) {
          'system.theme' => $this->systemThemeConfig,
          'olivero.settings' => $themeSettingsConfig,
          'system.theme.global' => $globalThemeConfig,
          default => $this->createMock(ImmutableConfig::class),
        };
      });

    $this->themeExtensionList->method('getList')->willReturn([
      'olivero' => $this->createMock(Extension::class),
    ]);

    $service = $this->createThemeService();
    $result = $service->getThemeSettings('olivero');

    $this->assertTrue($result['success'], $result['error'] ?? 'Expected getThemeSettings() to succeed.');
  }

  public function testUpdateThemeSettingsAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createThemeService();
    $result = $service->updateThemeSettings('olivero', ['logo' => ['path' => 'new-logo.svg']]);

    $this->assertFalse($result['success']);
  }

}
