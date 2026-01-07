<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_config\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * @group mcp_tools_config
 */
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

}

