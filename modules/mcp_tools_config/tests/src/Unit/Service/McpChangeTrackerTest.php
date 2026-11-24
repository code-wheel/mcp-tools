<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_config\Unit\Service;

use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools_config\Service\McpChangeTracker;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for McpChangeTracker.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_config\Service\McpChangeTracker::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_config')]
final class McpChangeTrackerTest extends UnitTestCase {

  private StateInterface $state;
  private McpChangeTracker $tracker;

  protected function setUp(): void {
    parent::setUp();
    $this->state = $this->createMock(StateInterface::class);
    $this->tracker = new McpChangeTracker($this->state);
  }

  public function testTrackChangeAddsNewEntry(): void {
    $this->state->method('get')->willReturn([]);

    $this->state->expects($this->once())
      ->method('set')
      ->with(
        'mcp_tools.config_changes',
        $this->callback(function (array $changes): bool {
          return count($changes) === 1
            && $changes[0]['config_name'] === 'system.site'
            && $changes[0]['operation'] === 'update';
        })
      );

    $this->tracker->trackChange('system.site', 'update');
  }

  public function testTrackChangeUpdatesExistingEntry(): void {
    $existingChanges = [
      [
        'config_name' => 'system.site',
        'operation' => 'create',
        'timestamp' => time() - 3600,
      ],
    ];
    $this->state->method('get')->willReturn($existingChanges);

    $this->state->expects($this->once())
      ->method('set')
      ->with(
        'mcp_tools.config_changes',
        $this->callback(function (array $changes): bool {
          // Should have same count but updated operation.
          return count($changes) === 1
            && $changes[0]['config_name'] === 'system.site'
            && $changes[0]['operation'] === 'update';
        })
      );

    $this->tracker->trackChange('system.site', 'update');
  }

  public function testGetTrackedChangesReturnsAll(): void {
    $changes = [
      ['config_name' => 'system.site', 'operation' => 'update', 'timestamp' => time()],
      ['config_name' => 'views.view.content', 'operation' => 'create', 'timestamp' => time()],
    ];
    $this->state->method('get')->willReturn($changes);

    $result = $this->tracker->getMcpChanges();

    $this->assertTrue($result['success']);
    $this->assertCount(2, $result['data']['changes']);
    $this->assertSame(2, $result['data']['total']);
  }

  /**
   * Each change is given once, with a time a reader can read.
   *
   * On the full site the answer was 9,242 characters: every change twice
   * (in by_operation and in changes), and each time twice, as a number and
   * as text. by_operation now names the config of each operation.
   */
  public function testEachChangeIsGivenOnce(): void {
    $this->state->method('get')->willReturn([
      ['config_name' => 'system.site', 'operation' => 'update', 'timestamp' => 1795501137],
      ['config_name' => 'views.view.content', 'operation' => 'create', 'timestamp' => 1795501130],
      ['config_name' => 'system.menu.main', 'operation' => 'update', 'timestamp' => 1795501135],
    ]);

    $data = $this->tracker->getMcpChanges()['data'];

    $this->assertSame(['config_name' => 'system.site', 'operation' => 'update', 'time' => '2026-11-24T06:18:57+00:00'], $data['changes'][0]);
    $this->assertSame(['update' => ['system.site', 'system.menu.main'], 'create' => ['views.view.content']], $data['by_operation']);
  }

  public function testGetTrackedChangesFiltersEmpty(): void {
    $this->state->method('get')->willReturn([]);

    $result = $this->tracker->getMcpChanges();

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['changes']);
    $this->assertSame(0, $result['data']['total']);
  }

  public function testClearTrackedChanges(): void {
    $this->state->expects($this->once())
      ->method('delete')
      ->with('mcp_tools.config_changes');

    // Method returns void, just verify no exception is thrown.
    $this->tracker->clearMcpChanges();
    $this->assertTrue(TRUE);
  }

}
