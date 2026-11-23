<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_layout_builder\Kernel;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * A tool does not change a layout an editor has unsaved changes to.
 *
 * Layout Builder keeps an editor's changes in its tempstore, and their
 * save writes the whole layout back: a section a tool added in between was
 * gone, and the tool had said success.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_layout_builder')]
final class UnsavedLayoutKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'layout_discovery', 'layout_builder', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_layout_builder'];

  /**
   * An editor's unsaved copy, then the tool's change.
   */
  public function testAnEditorsUnsavedLayoutIsNotOverwritten(): void {
    $this->installSchema('dblog', ['watchdog']);
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'filter', 'node', 'mcp_tools']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $service = $this->container->get('mcp_tools_layout_builder.layout_builder');
    $this->assertTrue($service->enableLayoutBuilder('node', 'page')['success']);

    // An editor opens the layout and changes it, and has not saved.
    $display = $this->container->get('entity_type.manager')->getStorage('entity_view_display')->load('node.page.default');
    $storage = $this->container->get('plugin.manager.layout_builder.section_storage')->load('defaults', [
      'display' => EntityContext::fromEntity($display),
      'view_mode' => new Context(new ContextDefinition('string'), 'default'),
    ]);
    $tempstore = $this->container->get('layout_builder.tempstore_repository');
    $tempstore->set($storage);
    $before = count($display->getSections());

    $result = $service->addSection('node', 'page', 'layout_onecol', 0);
    $this->assertFalse($result['success'], 'A tool changed a layout an editor was changing; their save would have undone it.');
    $this->assertSame('LAYOUT_BEING_EDITED', $result['code'] ?? NULL);
    $display = $this->container->get('entity_type.manager')->getStorage('entity_view_display')->loadUnchanged('node.page.default');
    $this->assertCount($before, $display->getSections());

    // Once they save or discard, the tool may.
    $tempstore->delete($storage);
    $this->assertTrue($service->addSection('node', 'page', 'layout_onecol', 0)['success']);
  }

}
