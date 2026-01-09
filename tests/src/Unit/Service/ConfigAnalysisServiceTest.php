<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\StorageInterface;
use Drupal\mcp_tools\Service\ConfigAnalysisService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for ConfigAnalysisService.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\ConfigAnalysisService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
class ConfigAnalysisServiceTest extends UnitTestCase {

  public function testGetConfigRedactsSensitiveKeysByDefault(): void {
    $targetConfig = $this->createMock(Config::class);
    $targetConfig->method('isNew')->willReturn(FALSE);
    $targetConfig->method('getRawData')->willReturn([
      'normal' => 'ok',
      'api_key' => 'abc123',
      'nested' => [
        'password' => 'secret',
        'token_value' => 'tkn',
      ],
    ]);

    $settingsConfig = $this->createMock(ImmutableConfig::class);
    $settingsConfig->method('get')
      ->with('output.include_sensitive')
      ->willReturn(FALSE);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->willReturnCallback(function (string $name) use ($targetConfig, $settingsConfig) {
        return $name === 'mcp_tools.settings' ? $settingsConfig : $targetConfig;
      });

    $activeStorage = $this->createMock(StorageInterface::class);
    $syncStorage = $this->createMock(StorageInterface::class);

    $service = new ConfigAnalysisService($configFactory, $activeStorage, $syncStorage);
    $result = $service->getConfig('system.site');

    $this->assertArrayHasKey('data', $result);
    $this->assertEquals('ok', $result['data']['normal']);
    $this->assertEquals('[REDACTED]', $result['data']['api_key']);
    $this->assertEquals('[REDACTED]', $result['data']['nested']['password']);
    $this->assertEquals('[REDACTED]', $result['data']['nested']['token_value']);
  }

  public function testGetConfigIncludesSensitiveWhenEnabled(): void {
    $targetConfig = $this->createMock(Config::class);
    $targetConfig->method('isNew')->willReturn(FALSE);
    $targetConfig->method('getRawData')->willReturn([
      'api_key' => 'abc123',
    ]);

    $settingsConfig = $this->createMock(ImmutableConfig::class);
    $settingsConfig->method('get')
      ->with('output.include_sensitive')
      ->willReturn(TRUE);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->willReturnCallback(function (string $name) use ($targetConfig, $settingsConfig) {
        return $name === 'mcp_tools.settings' ? $settingsConfig : $targetConfig;
      });

    $activeStorage = $this->createMock(StorageInterface::class);
    $syncStorage = $this->createMock(StorageInterface::class);

    $service = new ConfigAnalysisService($configFactory, $activeStorage, $syncStorage);
    $result = $service->getConfig('system.site');

    $this->assertEquals('abc123', $result['data']['api_key']);
  }

  public function testGetConfigReturnsErrorWhenMissing(): void {
    $targetConfig = $this->createMock(Config::class);
    $targetConfig->method('isNew')->willReturn(TRUE);

    $settingsConfig = $this->createMock(ImmutableConfig::class);
    $settingsConfig->method('get')->willReturn(FALSE);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->willReturnCallback(function (string $name) use ($targetConfig, $settingsConfig) {
        return $name === 'mcp_tools.settings' ? $settingsConfig : $targetConfig;
      });

    $activeStorage = $this->createMock(StorageInterface::class);
    $syncStorage = $this->createMock(StorageInterface::class);

    $service = new ConfigAnalysisService($configFactory, $activeStorage, $syncStorage);
    $result = $service->getConfig('does.not.exist');

    $this->assertArrayHasKey('error', $result);
  }

  public function testListConfigReturnsAllNames(): void {
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $activeStorage = $this->createMock(StorageInterface::class);
    $activeStorage->method('listAll')
      ->with('')
      ->willReturn(['system.site', 'system.performance', 'node.type.article']);

    $syncStorage = $this->createMock(StorageInterface::class);

    $service = new ConfigAnalysisService($configFactory, $activeStorage, $syncStorage);
    $result = $service->listConfig();

    $this->assertNull($result['prefix']);
    $this->assertSame(3, $result['total']);
    $this->assertCount(3, $result['names']);
    $this->assertContains('system.site', $result['names']);
  }

  public function testListConfigFiltersWithPrefix(): void {
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $activeStorage = $this->createMock(StorageInterface::class);
    $activeStorage->method('listAll')
      ->with('system.')
      ->willReturn(['system.site', 'system.performance']);

    $syncStorage = $this->createMock(StorageInterface::class);

    $service = new ConfigAnalysisService($configFactory, $activeStorage, $syncStorage);
    $result = $service->listConfig('system.');

    $this->assertSame('system.', $result['prefix']);
    $this->assertSame(2, $result['total']);
    $this->assertCount(2, $result['names']);
  }

  public function testGetOverridesReturnsEmptyWhenNoOverrides(): void {
    $configMock = $this->createMock(ImmutableConfig::class);
    $configMock->method('hasOverrides')->willReturn(FALSE);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($configMock);

    $activeStorage = $this->createMock(StorageInterface::class);
    $syncStorage = $this->createMock(StorageInterface::class);

    $service = new ConfigAnalysisService($configFactory, $activeStorage, $syncStorage);
    $result = $service->getOverrides();

    $this->assertSame(4, $result['total_checked']);
    $this->assertSame(0, $result['overridden_count']);
    $this->assertEmpty($result['overridden']);
    $this->assertArrayHasKey('note', $result);
  }

  public function testGetOverridesReturnsOverriddenConfigs(): void {
    $siteConfig = $this->createMock(ImmutableConfig::class);
    $siteConfig->method('hasOverrides')->willReturn(TRUE);

    $otherConfig = $this->createMock(ImmutableConfig::class);
    $otherConfig->method('hasOverrides')->willReturn(FALSE);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->willReturnCallback(function (string $name) use ($siteConfig, $otherConfig) {
        return $name === 'system.site' ? $siteConfig : $otherConfig;
      });

    $activeStorage = $this->createMock(StorageInterface::class);
    $syncStorage = $this->createMock(StorageInterface::class);

    $service = new ConfigAnalysisService($configFactory, $activeStorage, $syncStorage);
    $result = $service->getOverrides();

    $this->assertSame(4, $result['total_checked']);
    $this->assertSame(1, $result['overridden_count']);
    $this->assertCount(1, $result['overridden']);
    $this->assertSame('system.site', $result['overridden'][0]['name']);
    $this->assertTrue($result['overridden'][0]['has_overrides']);
  }

  /**
   * Tests getConfigStatus error handling.
   *
   * Note: Full getConfigStatus testing requires kernel tests due to
   * StorageComparer's internal dependencies on CachedStorage.
   */
  public function testGetConfigStatusReturnsErrorOnException(): void {
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $activeStorage = $this->createMock(StorageInterface::class);

    // Create a sync storage that throws an exception on any method call.
    $syncStorage = $this->createMock(StorageInterface::class);
    $syncStorage->method('listAll')->willThrowException(new \RuntimeException('Storage unavailable'));
    $syncStorage->method('getAllCollectionNames')->willThrowException(new \RuntimeException('Storage unavailable'));

    $service = new ConfigAnalysisService($configFactory, $activeStorage, $syncStorage);
    $result = $service->getConfigStatus();

    $this->assertArrayHasKey('error', $result);
    $this->assertStringContainsString('Unable to compare configuration', $result['error']);
    $this->assertFalse($result['has_changes']);
  }

}

