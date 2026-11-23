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

}
