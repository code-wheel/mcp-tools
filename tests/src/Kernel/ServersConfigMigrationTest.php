<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the mcp_tools.servers config migration (#3613059).
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
class ServersConfigMigrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mcp_tools',
    'tool',
    'user',
    'system',
    'update',
    'dblog',
  ];

  /**
   * The legacy name has no schema (and no owning module) by design.
   *
   * @var string[]
   */
  protected static $configSchemaCheckerExclusions = [
    'mcp_tools_servers.settings',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('module_handler')->loadInclude('mcp_tools', 'install');
  }

  /**
   * Legacy mcp_tools_servers.settings data moves to mcp_tools.servers.
   */
  public function testUpdate10001MigratesLegacyConfig(): void {
    $legacy = [
      'default_server' => 'development',
      'servers' => [
        'development' => [
          'name' => 'Legacy Dev',
          'scopes' => ['read', 'write'],
        ],
      ],
    ];
    $this->config('mcp_tools_servers.settings')->setData($legacy)->save();

    mcp_tools_update_10001();

    $factory = $this->container->get('config.factory');
    $this->assertSame($legacy, $factory->get('mcp_tools.servers')->getRawData());
    $this->assertTrue($factory->get('mcp_tools_servers.settings')->isNew(), 'The legacy config object was deleted.');

    // The migrated profile is what the repository serves.
    $servers = $this->container->get('mcp_tools.server_config_repository')->getServers();
    $this->assertSame('Legacy Dev', $servers['development']['name']);
  }

  /**
   * The update hook is a no-op without legacy config.
   */
  public function testUpdate10001WithoutLegacyConfigIsNoOp(): void {
    $this->installConfig(['mcp_tools']);
    $before = $this->config('mcp_tools.servers')->getRawData();

    mcp_tools_update_10001();

    $this->assertSame($before, $this->config('mcp_tools.servers')->getRawData());
    $this->assertTrue($this->container->get('config.factory')->get('mcp_tools_servers.settings')->isNew());
  }

}
