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

  /**
   * The diff, a read tool, gives no secrets: get_config already did not.
   */
  public function testTheDiffGivesNoSecrets(): void {
    $this->activeStorage->method('read')->willReturnMap([
      ['mail.new', ['host' => 'smtp.example.com', 'smtp_password' => 'SECRET-1', 'credentials' => ['user' => 'u', 'value' => 'SECRET-2']]],
      ['mail.changed', ['host' => 'b', 'api_key' => 'SECRET-NEW']],
    ]);
    $this->syncStorage->method('read')->willReturnMap([
      ['mail.new', FALSE],
      ['mail.changed', ['host' => 'a', 'api_key' => 'SECRET-OLD']],
    ]);
    foreach (['mail.new', 'mail.changed'] as $name) {
      $out = json_encode($this->service->getConfigDiff($name));
      $this->assertStringNotContainsString('SECRET', $out, "$name gave a secret: $out");
      $this->assertStringContainsString('[REDACTED]', $out);
    }
    // What is not a secret is still shown, and a changed secret still
    // shows as a change.
    $changed = $this->service->getConfigDiff('mail.changed');
    $this->assertContains('api_key', array_column($changed['data']['diff'], 'path'));
    $this->assertSame('b', $changed['data']['active_data']['host']);
  }

}
