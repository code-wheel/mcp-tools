<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_menus\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * A menu link to a path where nothing is says so.
 *
 * The same fault as the redirect tools had: a model knows a page by its
 * title, makes its path up, and the link is stored with "success".
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_menus')]
final class MenuLinkTargetKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'link', 'menu_link_content', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_menus'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('menu_link_content');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'filter', 'mcp_tools']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    Node::create(['type' => 'page', 'title' => 'Pricing page', 'status' => 1])->save();
    $menus = $this->container->get('entity_type.manager')->getStorage('menu');
    if (!$menus->load('main')) {
      $menus->create(['id' => 'main', 'label' => 'Main'])->save();
    }
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * A made-up path gets a warning, a real one none, an update too.
   */
  public function testATargetWhereNothingIsGetsAWarning(): void {
    $service = $this->container->get('mcp_tools_menus.menu');

    $made = $service->addMenuLink('main', 'Pricing', '/pricing-page');
    $this->assertTrue($made['success'], (string) ($made['error'] ?? ''));
    $this->assertArrayHasKey('warning', $made['data'], 'A link to a path where nothing is said success and nothing else.');
    $this->assertStringContainsString('/pricing-page', $made['data']['warning']);
    $this->assertStringContainsString('mcp_menus_update_menu_link', $made['data']['warning']);
    $this->assertStringContainsString($made['data']['warning'], $made['data']['message']);

    foreach (['/node/1', 'node/1', '<front>', 'https://example.com/x', 'entity:node/1'] as $n => $uri) {
      $ok = $service->addMenuLink('main', 'Fine ' . $n, $uri);
      $this->assertTrue($ok['success'], $uri . ': ' . ($ok['error'] ?? ''));
      $this->assertArrayNotHasKey('warning', $ok['data'], $uri . ' got: ' . ($ok['data']['warning'] ?? ''));
    }

    $updated = $service->updateMenuLink((int) $made['data']['id'], ['uri' => '/node/1']);
    $this->assertTrue($updated['success'], (string) ($updated['error'] ?? ''));
    $this->assertArrayNotHasKey('warning', $updated['data']);
    $updated = $service->updateMenuLink((int) $made['data']['id'], ['uri' => '/no/such/page']);
    $this->assertArrayHasKey('warning', $updated['data']);
    $updated = $service->updateMenuLink((int) $made['data']['id'], ['title' => 'Prices']);
    $this->assertArrayNotHasKey('warning', $updated['data'], 'A change of the title is not a change of the target.');
  }

  /**
   * A parent must exist and be in the same menu.
   *
   * Drupal put a link with an unknown parent at the top, and one with a
   * parent in another menu into that menu; the answer named the menu
   * that was asked.
   */
  public function testAParentMustBeThere(): void {
    $service = $this->container->get('mcp_tools_menus.menu');
    $this->container->get('entity_type.manager')->getStorage('menu')->create(['id' => 'probe_menu', 'label' => 'Probe menu'])->save();
    $top = $service->addMenuLink('main', 'Top', '/node/1');
    $this->assertFalse($service->addMenuLink('main', 'Child', '/node/1', ['parent' => '999'])['success'], 'A parent that does not exist was taken.');
    $this->assertFalse($service->addMenuLink('probe_menu', 'Child', '/node/1', ['parent' => $top['data']['plugin_id']])['success'], 'A parent in another menu was taken.');
    $child = $service->addMenuLink('main', 'Child', '/node/1', ['parent' => (string) $top['data']['id']]);
    $this->assertTrue($child['success'], (string) ($child['error'] ?? ''));
    $this->assertSame($top['data']['plugin_id'], \Drupal\menu_link_content\Entity\MenuLinkContent::load($child['data']['id'])->getParentId());
  }

  /**
   * The tree gives what a parent is given as, so a model can use it.
   *
   * A wrong parent is refused with "Give the plugin_id ... or its id", and
   * the tree showed neither: a model could only guess.
   */
  public function testTheTreeGivesTheIdOfAParent(): void {
    $links = $this->container->get('mcp_tools_menus.menu');
    $this->assertTrue($links->addMenuLink('main', 'About', '/node/1')['success']);

    $tree = $this->container->get('mcp_tools.menu')->getMenuTree('main');
    $about = current(array_filter($tree['tree'], fn($item) => $item['title'] === 'About'));
    $this->assertIsArray($about, json_encode($tree));
    $this->assertStringStartsWith('menu_link_content:', $about['plugin_id']);
    $this->assertIsInt($about['id']);

    foreach (['plugin_id', 'id'] as $n => $key) {
      $child = $links->addMenuLink('main', 'Team ' . $n, '/node/1', ['parent' => (string) $about[$key]]);
      $this->assertTrue($child['success'], $key . ': ' . (string) ($child['error'] ?? ''));
    }
    $tree = $this->container->get('mcp_tools.menu')->getMenuTree('main');
    $about = current(array_filter($tree['tree'], fn($item) => $item['title'] === 'About'));
    $this->assertSame(['Team 0', 'Team 1'], array_column($about['children'], 'title'));
  }

}
