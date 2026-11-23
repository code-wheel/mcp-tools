<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_blocks\Kernel;

use Drupal\block\Entity\Block;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Visibility and settings of a block, as the two block tools take them.
 *
 * Placing a block took visibility keyed by condition; configuring one
 * wanted one condition with a "condition_id" key, and given the place
 * tool's shape it stored the wrong thing and said success. Both tools
 * merged in any setting, also one the block does not have.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_blocks')]
final class BlockInputsKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'path_alias', 'block', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_blocks'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'mcp_tools']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    \Drupal\node\Entity\NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * The stored visibility of a block, reloaded.
   */
  private function visibility(string $id): array {
    $this->container->get('entity_type.manager')->getStorage('block')->resetCache([$id]);
    return Block::load($id)->getVisibility();
  }

  /**
   * One shape of visibility for both tools, and the old one still read.
   */
  public function testVisibilityHasOneShape(): void {
    $service = $this->container->get('mcp_tools_blocks.block');
    $placed = $service->placeBlock('system_powered_by_block', 'content', ['id' => 'powered', 'visibility' => ['request_path' => ['pages' => '/about']]]);
    $this->assertTrue($placed['success'], (string) ($placed['error'] ?? ''));
    $this->assertSame('/about', $this->visibility('powered')['request_path']['pages']);

    // The place tool's shape, given to the configure tool.
    $configured = $service->configureBlock('powered', ['visibility' => ['request_path' => ['pages' => ['/contact', '/help']]]]);
    $this->assertTrue($configured['success'], (string) ($configured['error'] ?? ''));
    $stored = $this->visibility('powered');
    $this->assertSame("/contact\n/help", $stored['request_path']['pages'] ?? NULL, 'The place tool\'s shape was stored wrong: ' . json_encode($stored));
    $this->assertSame('request_path', $stored['request_path']['id']);

    // Roles as a list, and the conditions replace the ones there were.
    $service->configureBlock('powered', ['visibility' => ['user_role' => ['roles' => ['authenticated']]]]);
    $stored = $this->visibility('powered');
    $this->assertSame(['authenticated' => 'authenticated'], $stored['user_role']['roles']);
    $this->assertArrayNotHasKey('request_path', $stored);

    // Pages of one content type, under its old name as well.
    foreach (['entity_bundle:node', 'node_type'] as $name) {
      $this->assertTrue($service->configureBlock('powered', ['visibility' => [$name => ['bundles' => ['article']]]])['success']);
      $stored = $this->visibility('powered');
      $this->assertSame(['article' => 'article'], $stored['entity_bundle:node']['bundles'] ?? NULL, $name . ': ' . json_encode($stored));
      $this->assertSame('@node.node_route_context:node', $stored['entity_bundle:node']['context_mapping']['node']);
    }

    // The configure tool's old shape still works.
    $service->configureBlock('powered', ['visibility' => ['condition_id' => 'request_path', 'pages' => '/old']]);
    $this->assertSame('/old', $this->visibility('powered')['request_path']['pages']);
  }

  /**
   * A setting the block does not have is refused, by both tools.
   */
  public function testASettingTheBlockDoesNotHaveIsRefused(): void {
    $service = $this->container->get('mcp_tools_blocks.block');
    $placed = $service->placeBlock('system_powered_by_block', 'content', ['id' => 'p2', 'settings' => ['items_per_page' => 5]]);
    $this->assertFalse($placed['success'], 'A block was placed with a setting it does not have.');
    $this->assertStringContainsString('items_per_page', $placed['error']);
    $this->assertNull(Block::load('p2'));

    $this->assertTrue($service->placeBlock('system_powered_by_block', 'content', ['id' => 'p3', 'settings' => ['label' => 'Powered']])['success']);
    $configured = $service->configureBlock('p3', ['settings' => ['no_such_setting' => 1]]);
    $this->assertFalse($configured['success'], 'A setting the block does not have was taken.');
    $this->assertStringContainsString('no_such_setting', $configured['error']);
    $this->assertTrue($service->configureBlock('p3', ['settings' => ['label_display' => '0']])['success']);
  }

  /**
   * The flat shape the release took is read, and a page must be a path.
   *
   * Seen in the framework's scoring: {"pages": "/user/login"} worked on the
   * release and was refused after the fix; "\\/user/register" was stored.
   */
  public function testFlatVisibilityAndPagesThatArePaths(): void {
    $service = $this->container->get('mcp_tools_blocks.block');
    $this->assertTrue($service->placeBlock('system_powered_by_block', 'content', ['id' => 'flat'])['success']);

    $result = $service->configureBlock('flat', ['visibility' => ['pages' => '/user/login']]);
    $this->assertTrue($result['success'], 'The flat page shape of the release was refused: ' . ($result['error'] ?? ''));
    $this->assertSame('/user/login', $this->visibility('flat')['request_path']['pages']);

    $service->configureBlock('flat', ['visibility' => ['pages' => '\\/user\\/register']]);
    $this->assertSame('/user/register', $this->visibility('flat')['request_path']['pages']);

    $bad = $service->configureBlock('flat', ['visibility' => ['request_path' => ['pages' => 'user/login']]]);
    $this->assertFalse($bad['success'], 'A page that is not a path was stored.');
    $this->assertSame('/user/register', $this->visibility('flat')['request_path']['pages']);

    $id = $service->configureBlock('flat', ['settings' => ['provider' => 'x']]);
    $this->assertFalse($id['success'], 'The provider was taken as a setting.');
    $this->assertStringNotContainsString('provider', explode('Its settings are:', $id['error'])[1]);
  }

  /**
   * A hidden label is hidden: Drupal shows any label_display not empty.
   */
  public function testAHiddenLabelIsHidden(): void {
    $service = $this->container->get('mcp_tools_blocks.block');
    $this->assertTrue($service->placeBlock('system_powered_by_block', 'content', ['id' => 'quiet', 'label' => 'Shh', 'label_display' => 'hidden'])['success']);
    $this->container->get('entity_type.manager')->getStorage('block')->resetCache(['quiet']);
    $this->assertSame('0', \Drupal\block\Entity\Block::load('quiet')->get('settings')['label_display'], 'label_display "hidden" was stored as a value Drupal shows.');
    $this->assertFalse($service->configureBlock('quiet', ['label_display' => 'sometimes'])['success']);
  }

}
