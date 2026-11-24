<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_sitemap\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Entity settings keep what was not given, in every variant.
 *
 * A call with only changefreq turned indexing on and reset the priority,
 * only the first variant was written, and a bundle that does not exist
 * was "updated".
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_sitemap')]
final class EntitySettingsKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'path_alias', 'simple_sitemap', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_sitemap'];

  /**
   * Two variants, one call with one setting.
   */
  public function testSettingsAreMergedIntoEveryVariant(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('simple_sitemap');
    $this->installSchema('simple_sitemap', ['simple_sitemap', 'simple_sitemap_entity_overrides']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'node', 'simple_sitemap', 'mcp_tools']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $storage = $this->container->get('entity_type.manager')->getStorage('simple_sitemap');
    if (!$storage->load('second')) {
      $storage->create(['id' => 'second', 'label' => 'Second', 'type' => 'default_hreflang'])->save();
    }
    $service = $this->container->get('mcp_tools_sitemap.sitemap');

    $this->assertTrue($service->setEntitySettings('node', 'page', ['index' => FALSE, 'priority' => '0.9'])['success']);
    $this->assertTrue($service->setEntitySettings('node', 'page', ['changefreq' => 'weekly'])['success']);
    foreach (['default', 'second'] as $variant) {
      $config = $this->config("simple_sitemap.bundle_settings.$variant.node.page");
      $this->assertSame([FALSE, '0.9', 'weekly'], [$config->get('index'), $config->get('priority'), $config->get('changefreq')], "Variant $variant does not hold what was set.");
    }
    $this->assertFalse($service->setEntitySettings('node', 'no_such_type', ['index' => TRUE])['success'], 'A bundle that does not exist was updated.');
  }

  /**
   * A key the tools do not read is refused, and nothing is changed.
   *
   * setEntitySettings kept the four keys it knows and dropped the rest;
   * updateSettings ignored anything but global, enabled and label, and
   * unknown keys under global. Each said success.
   */
  public function testAKeyTheToolsDoNotReadIsRefused(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('simple_sitemap');
    $this->installSchema('simple_sitemap', ['simple_sitemap', 'simple_sitemap_entity_overrides']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'node', 'simple_sitemap', 'mcp_tools']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $service = $this->container->get('mcp_tools_sitemap.sitemap');
    $before = $this->config('simple_sitemap.bundle_settings.default.node.page')->get('index');

    $result = $service->setEntitySettings('node', 'page', ['index' => !$before, 'prioirty' => '0.8']);
    $this->assertFalse($result['success'], 'A misspelled key was dropped with success.');
    $this->assertStringContainsString('not prioirty', $result['error']);
    $this->assertSame($before, $this->config('simple_sitemap.bundle_settings.default.node.page')->get('index'), 'The known key was set although the call was refused.');

    $maxLinks = $this->config('simple_sitemap.settings')->get('max_links');
    foreach ([['max_links' => 7], ['global' => ['max_link' => 7]]] as $settings) {
      $result = $service->updateSettings('default', $settings);
      $this->assertFalse($result['success'], json_encode($settings) . ' changed nothing and said success.');
    }
    $this->assertStringContainsString('global.max_link', $result['error']);
    $this->assertSame($maxLinks, $this->config('simple_sitemap.settings')->get('max_links'));
    $this->assertTrue($service->updateSettings('default', ['global' => ['max_links' => 7]])['success']);
    $this->assertSame(7, $this->config('simple_sitemap.settings')->get('max_links'));
  }

}
