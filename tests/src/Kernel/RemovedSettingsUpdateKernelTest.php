<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Settings of removed features leave the site with the update.
 *
 * Webhook notifications were configurable and documented and never sent
 * anything: nothing called the service. The feature is gone. A site that
 * filled in the form keeps a "webhooks" block in its config, with a secret
 * in it, that no schema describes any more.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class RemovedSettingsUpdateKernelTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['mcp_tools']);
    $this->container->get('module_handler')->loadInclude('mcp_tools', 'install');
  }

  /**
   * The webhook settings of an older site are removed, the rest stays.
   */
  public function testWebhookSettingsAreRemoved(): void {
    // As an older release stored it. Written to the storage: the schema of
    // this release does not know the block.
    $storage = $this->container->get('config.storage');
    $data = $storage->read('mcp_tools.settings');
    $data['access']['read_only_mode'] = TRUE;
    $data['webhooks'] = [
      'enabled' => TRUE,
      'url' => 'https://hooks.example.com/mcp',
      'secret' => 'a-secret',
      'allowed_hosts' => [],
      'timeout' => 5,
      'batch_notifications' => TRUE,
      'notify_on' => ['create', 'delete'],
    ];
    $storage->write('mcp_tools.settings', $data);
    $this->container->get('config.factory')->reset('mcp_tools.settings');

    $this->assertTrue(function_exists('mcp_tools_update_10002'), 'There is an update for sites that have the old settings.');
    mcp_tools_update_10002();

    $after = $this->container->get('config.storage')->read('mcp_tools.settings');
    $this->assertArrayNotHasKey('webhooks', $after);
    $this->assertTrue($after['access']['read_only_mode'], 'Other settings are kept.');
    // What is left fits the schema: saving it again raises nothing.
    $this->config('mcp_tools.settings')->save();
  }

  /**
   * A new install has no webhook settings, and the update changes nothing.
   */
  public function testNewInstallHasNoWebhookSettings(): void {
    $before = $this->container->get('config.storage')->read('mcp_tools.settings');
    $this->assertArrayNotHasKey('webhooks', $before);
    $this->assertFalse($this->container->has('mcp_tools.webhook_notifier'));

    $this->assertTrue(function_exists('mcp_tools_update_10002'));
    mcp_tools_update_10002();
    $this->assertSame($before, $this->container->get('config.storage')->read('mcp_tools.settings'));
  }

  /**
   * The "public components only" switch of a server profile is removed.
   *
   * It belonged to a way of adding tools by hook that nothing ever read:
   * the hook was never invoked. The switch did nothing either.
   */
  public function testComponentSwitchIsRemovedFromProfiles(): void {
    $installed = $this->container->get('config.storage')->read('mcp_tools.servers');
    foreach ($installed['servers'] as $id => $profile) {
      $this->assertArrayNotHasKey('component_public_only', $profile, "new install, profile $id");
    }

    // As an older release stored it, with a profile of the site's own.
    $storage = $this->container->get('config.storage');
    $data = $installed;
    foreach (array_keys($data['servers']) as $id) {
      $data['servers'][$id]['component_public_only'] = FALSE;
    }
    $data['servers']['mine'] = ['name' => 'Mine', 'scopes' => ['read'], 'component_public_only' => TRUE];
    $storage->write('mcp_tools.servers', $data);
    $this->container->get('config.factory')->reset('mcp_tools.servers');

    $this->assertTrue(function_exists('mcp_tools_update_10003'), 'There is an update for sites that have the old profiles.');
    mcp_tools_update_10003();

    $after = $this->container->get('config.storage')->read('mcp_tools.servers');
    foreach ($after['servers'] as $id => $profile) {
      $this->assertArrayNotHasKey('component_public_only', $profile, "after the update, profile $id");
    }
    $this->assertSame('Mine', $after['servers']['mine']['name'], 'The rest of a profile is kept.');
    $this->assertSame(['read'], $after['servers']['mine']['scopes']);
    // What is left fits the schema, and the repository serves it.
    $this->config('mcp_tools.servers')->save();
    $this->assertArrayNotHasKey('component_public_only', $this->container->get('mcp_tools.server_config_repository')->getServer('mine'));
  }

}
