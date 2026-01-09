<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_config\Unit\Service;

use Drupal\Core\Config\StorageInterface;
use Drupal\mcp_tools_config\Service\ConfigComparisonService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for ConfigComparisonService.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_config\Service\ConfigComparisonService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_config')]
final class ConfigComparisonServiceTest extends UnitTestCase {

  private StorageInterface $activeStorage;
  private StorageInterface $syncStorage;
  private ConfigComparisonService $service;

  protected function setUp(): void {
    parent::setUp();
    $this->activeStorage = $this->createMock(StorageInterface::class);
    $this->syncStorage = $this->createMock(StorageInterface::class);

    $this->service = new ConfigComparisonService(
      $this->activeStorage,
      $this->syncStorage,
    );
  }

  /**
   * Tests getConfigChanges error handling.
   *
   * Note: Full getConfigChanges testing requires kernel tests due to
   * StorageComparer's internal dependencies on CachedStorage.
   */
  public function testGetConfigChangesHandlesExceptionGracefully(): void {
    // Make the storage throw an exception to test error handling.
    $this->activeStorage->method('listAll')->willThrowException(new \RuntimeException('Storage unavailable'));

    $result = $this->service->getConfigChanges();

    $this->assertFalse($result['success']);
    $this->assertArrayHasKey('error', $result);
    $this->assertStringContainsString('Unable to compare configuration', $result['error']);
  }

  public function testDiffConfigReturnsErrorForNonexistent(): void {
    $this->activeStorage->method('read')->with('nonexistent')->willReturn(FALSE);
    $this->syncStorage->method('read')->with('nonexistent')->willReturn(FALSE);

    $result = $this->service->getConfigDiff('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('does not exist', $result['error']);
  }

  public function testDiffConfigShowsDifferences(): void {
    $activeData = ['name' => 'Test Site', 'page' => ['front' => '/node']];
    $syncData = ['name' => 'Old Name', 'page' => ['front' => '/']];

    $this->activeStorage->method('read')->with('system.site')->willReturn($activeData);
    $this->syncStorage->method('read')->with('system.site')->willReturn($syncData);

    $result = $this->service->getConfigDiff('system.site');

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('diff', $result['data']);
    $this->assertSame('system.site', $result['data']['config_name']);
  }

  public function testDiffConfigNewConfig(): void {
    $activeData = ['name' => 'New Config'];

    $this->activeStorage->method('read')->with('new.config')->willReturn($activeData);
    $this->syncStorage->method('read')->with('new.config')->willReturn(FALSE);

    $result = $this->service->getConfigDiff('new.config');

    $this->assertTrue($result['success']);
    $this->assertSame('new_in_active', $result['data']['status']);
  }

}
