<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_config\Kernel;

use Drupal\KernelTests\KernelTestBase;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_config')]
final class ConfigChangeTrackingKernelTest extends KernelTestBase {

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
    'mcp_tools_config',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['mcp_tools']);
    $this->installConfig(['system']);
  }

  public function testDoesNotTrackOutsideToolCallContext(): void {
    $state = $this->container->get('state');
    $state->delete('mcp_tools.config_changes');

    $this->config('system.site')->set('name', 'Outside')->save();

    $changes = $state->get('mcp_tools.config_changes', []);
    $this->assertSame([], $changes);
  }

  public function testTracksConfigSaveInsideToolCallContext(): void {
    $state = $this->container->get('state');
    $state->delete('mcp_tools.config_changes');

    $context = $this->container->get('mcp_tools.tool_call_context');
    $context->enter();
    try {
      $this->config('system.site')->set('name', 'Inside')->save();
    }
    finally {
      $context->leave();
    }

    $changes = $state->get('mcp_tools.config_changes', []);
    $this->assertNotEmpty($changes);

    $first = reset($changes);
    $this->assertIsArray($first);
    $this->assertSame('system.site', $first['config_name']);
    $this->assertContains($first['operation'], ['create', 'update']);
  }

  /**
   * The list of changes is what an export would write, read from real storage.
   *
   * This comparison had no test that ran: the unit test for it was a
   * placeholder that skipped itself.
   */
  public function testConfigChangesListWhatAnExportWouldWrite(): void {
    $service = $this->container->get('mcp_tools_config.config_comparison');
    $sync = $this->container->get('config.storage.sync');
    $this->copyConfig($this->container->get('config.storage'), $sync);

    $result = $service->getConfigChanges();
    $this->assertTrue($result['success']);
    $this->assertFalse($result['data']['has_changes']);
    $this->assertSame(0, $result['data']['total_changes']);

    // One config changed on the site, one missing from the sync directory.
    $this->config('system.site')->set('name', 'Changed')->save();
    $sync->delete('system.performance');

    $result = $service->getConfigChanges();
    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['has_changes']);
    $this->assertSame(2, $result['data']['total_changes']);
    $this->assertSame(['system.site'], $result['data']['changes']['update']);
    $this->assertSame(['system.performance'], $result['data']['changes']['create']);
    $this->assertSame(
      ['new_in_active' => 1, 'modified' => 1, 'deleted_from_active' => 0, 'renamed' => 0],
      $result['data']['summary'],
    );
  }

}
