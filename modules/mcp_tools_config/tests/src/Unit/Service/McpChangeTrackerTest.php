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
    $this->assertSame(2, $result['data']['total_changes']);
  }

  public function testGetTrackedChangesFiltersEmpty(): void {
    $this->state->method('get')->willReturn([]);

    $result = $this->tracker->getMcpChanges();

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['changes']);
    $this->assertSame(0, $result['data']['total_changes']);
  }

  public function testClearTrackedChanges(): void {
    $this->state->expects($this->once())
      ->method('delete')
      ->with('mcp_tools.config_changes');

    $result = $this->tracker->clearMcpChanges();

    $this->assertTrue($result['success']);
    $this->assertSame('All tracked MCP configuration changes have been cleared.', $result['data']['message']);
  }

}
