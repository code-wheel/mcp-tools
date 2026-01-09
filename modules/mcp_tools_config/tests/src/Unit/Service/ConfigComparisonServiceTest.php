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

  public function testGetConfigChangesReturnsStructuredResult(): void {
    // Mock no config in either storage.
    $this->activeStorage->method('listAll')->willReturn([]);
    $this->syncStorage->method('listAll')->willReturn([]);
    $this->activeStorage->method('getAllCollectionNames')->willReturn(['']);
    $this->syncStorage->method('getAllCollectionNames')->willReturn(['']);

    $result = $this->service->getConfigChanges();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('has_changes', $result['data']);
    $this->assertArrayHasKey('summary', $result['data']);
    $this->assertArrayHasKey('changes', $result['data']);
  }

  public function testDiffConfigReturnsNull(): void {
    $this->activeStorage->method('read')->with('nonexistent')->willReturn(FALSE);

    $result = $this->service->getConfigDiff('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
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
    $this->assertSame('new', $result['data']['status']);
  }

}
