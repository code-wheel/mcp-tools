<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_layout_builder\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * A field block knows which entity it shows, and a section where it went.
 *
 * Only the block id was saved, so a field block had no context mapping
 * and a page of the bundle could not render it. A section past the end
 * was appended, and the answer gave the position asked.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_layout_builder')]
final class FieldBlockKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'layout_discovery', 'layout_builder', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_layout_builder'];

  /**
   * The block is mapped, the page renders, the section says where it is.
   */
  public function testAFieldBlockRendersOnItsPage(): void {
    $this->installSchema('dblog', ['watchdog']);
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'filter', 'node', 'mcp_tools']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $service = $this->container->get('mcp_tools_layout_builder.layout_builder');
    $this->assertTrue($service->enableLayoutBuilder('node', 'page')['success']);

    $section = $service->addSection('node', 'page', 'layout_onecol', 99);
    $this->assertTrue($section['success'], (string) ($section['error'] ?? ''));
    $this->assertSame($section['data']['section_count'] - 1, $section['data']['delta'], 'The answer gave a position the section is not at.');

    $block = $service->addBlock('node', 'page', $section['data']['delta'], 'content', 'field_block:node:page:title');
    $this->assertTrue($block['success'], (string) ($block['error'] ?? ''));
    $display = $this->container->get('entity_type.manager')->getStorage('entity_view_display')->load('node.page.default');
    $component = $display->getSection($section['data']['delta'])->getComponent($block['data']['component_uuid']);
    $this->assertSame('layout_builder.entity', $component->get('configuration')['context_mapping']['entity'] ?? NULL, 'The field block was saved without the entity it shows.');

    $node = Node::create(['type' => 'page', 'title' => 'Shown title']);
    $node->save();
    $build = $this->container->get('entity_type.manager')->getViewBuilder('node')->view($node);
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Shown title', $html);
  }

}
