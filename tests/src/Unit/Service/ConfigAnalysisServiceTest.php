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

}

