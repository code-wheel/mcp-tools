<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_layout_builder\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Section;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Turning custom layouts off asks first when there are some.
 *
 * Drupal drops the field that holds them, so every custom layout of the
 * bundle is gone for good. Drupal's form asks; the tool did not.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_layout_builder')]
final class AllowCustomAsksKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'layout_discovery', 'layout_builder', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_layout_builder'];

  /**
   * A reason to ask with custom layouts there, none without or when on.
   */
  public function testItAsksBeforeDeletingLayouts(): void {
    $this->installSchema('dblog', ['watchdog']);
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'filter', 'node', 'mcp_tools']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $service = $this->container->get('mcp_tools_layout_builder.layout_builder');
    $this->assertTrue($service->enableLayoutBuilder('node', 'page')['success']);
    $this->assertTrue($service->allowCustomLayouts('node', 'page', TRUE)['success']);

    $tool = $this->container->get('plugin.manager.tool')->createInstance('mcp_layout_allow_custom');
    $this->assertInstanceOf(\Drupal\mcp_tools\Tool\AsksFirstInterface::class, $tool, 'Turning custom layouts off never asks.');
    $this->assertNull($tool->reasonToAsk(['entity_type' => 'node', 'bundle' => 'page', 'allow' => FALSE]), 'Nothing to lose, and it asked.');

    $node = Node::create(['type' => 'page', 'title' => 'Own layout']);
    $node->set('layout_builder__layout', [Section::fromArray(['layout_id' => 'layout_onecol', 'components' => []])]);
    $node->save();
    $reason = $tool->reasonToAsk(['entity_type' => 'node', 'bundle' => 'page', 'allow' => FALSE]);
    $this->assertStringContainsString('1 item', (string) $reason);
    $this->assertNull($tool->reasonToAsk(['entity_type' => 'node', 'bundle' => 'page', 'allow' => TRUE]));
  }

}
