<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools\Service\SiteHealthService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\SiteHealthService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class SiteHealthServiceTest extends UnitTestCase {

  public function testGetCronStatusReturnsUnknownWhenNeverRun(): void {
    $modules = $this->createMock(ModuleExtensionList::class);
    $modules->method('getAllInstalledInfo')->willReturn([]);

    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturnMap([
      ['system.cron_last', 0, 0],
    ]);

    $database = $this->createMock(Connection::class);

    $cronConfig = $this->createMock(ImmutableConfig::class);
    $cronConfig->method('get')->with('threshold.autorun')->willReturn(10800);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('system.cron')->willReturn($cronConfig);

    $service = new SiteHealthService(
      $modules,
      $state,
      $database,
      $configFactory,
      $this->createMock(ModuleHandlerInterface::class),
    );

    $status = $service->getCronStatus();
    $this->assertSame('Never', $status['last_run']);
    $this->assertSame('unknown', $status['status']);
    $this->assertNull($status['seconds_since_last_run']);
  }

  public function testGetInstalledModulesFiltersCoreByDefault(): void {
    $modules = $this->createMock(ModuleExtensionList::class);
    $modules->method('getAllInstalledInfo')->willReturn([
      'system' => ['status' => TRUE, 'package' => 'Core', 'name' => 'System'],
      'mcp_tools' => ['status' => TRUE, 'package' => 'Custom', 'name' => 'MCP Tools'],
      'views' => ['status' => TRUE, 'package' => 'Core', 'name' => 'Views'],
      'contrib_example' => ['status' => TRUE, 'package' => 'Contrib', 'name' => 'Contrib Example'],
    ]);

    $service = new SiteHealthService(
      $modules,
      $this->createMock(StateInterface::class),
      $this->createMock(Connection::class),
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
    );

    $withoutCore = $service->getInstalledModules(FALSE);
    $names = array_column($withoutCore, 'name');
    $this->assertContains('mcp_tools', $names);
    $this->assertContains('contrib_example', $names);
    $this->assertNotContains('system', $names);
    $this->assertNotContains('views', $names);

    $withCore = $service->getInstalledModules(TRUE);
    $namesWithCore = array_column($withCore, 'name');
    $this->assertContains('system', $namesWithCore);
    $this->assertContains('views', $namesWithCore);
  }

  public function testGetSiteStatusIncludesModuleSummary(): void {
    $modules = $this->createMock(ModuleExtensionList::class);
    $modules->method('getAllInstalledInfo')->willReturn([
      'system' => ['status' => TRUE, 'package' => 'Core'],
      'mcp_tools' => ['status' => TRUE, 'package' => 'Custom'],
      'contrib_example' => ['status' => TRUE, 'package' => 'Contrib'],
      'disabled' => ['status' => FALSE, 'package' => 'Core'],
    ]);

    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturnMap([
      ['system.cron_last', 0, 0],
      ['system.maintenance_mode', FALSE, FALSE],
    ]);

    $database = $this->createMock(Connection::class);
    $database->method('driver')->willReturn('sqlite');
    $database->method('version')->willReturn('3');

    $siteConfig = $this->createMock(ImmutableConfig::class);
    $siteConfig->method('get')->willReturnMap([
      ['name', 'Test site'],
      ['uuid', 'test-uuid'],
    ]);

    $extensionConfig = $this->createMock(ImmutableConfig::class);
    $extensionConfig->method('get')->with('profile')->willReturn('minimal');

    $cronConfig = $this->createMock(ImmutableConfig::class);
    $cronConfig->method('get')->with('threshold.autorun')->willReturn(10800);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturnMap([
      ['system.site', $siteConfig],
      ['core.extension', $extensionConfig],
      ['system.cron', $cronConfig],
    ]);

    $service = new SiteHealthService(
      $modules,
      $state,
      $database,
      $configFactory,
      $this->createMock(ModuleHandlerInterface::class),
    );

    $status = $service->getSiteStatus();
    $this->assertSame('Test site', $status['site_name']);
    $this->assertSame('test-uuid', $status['site_uuid']);

    $moduleSummary = $status['modules'];
    $this->assertSame(3, $moduleSummary['total_installed']);
    $this->assertSame(1, $moduleSummary['core_count']);
    $this->assertSame(1, $moduleSummary['custom_count']);
    $this->assertSame(1, $moduleSummary['contrib_count']);
  }

}

