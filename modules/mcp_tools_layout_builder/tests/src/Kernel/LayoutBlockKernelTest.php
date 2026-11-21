<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_layout_builder\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\node\Entity\NodeType;

/**
 * Adding a block to a layout and removing it, on real services.
 *
 * Both tools called setSection() on the display. That method is protected
 * on Drupal 10, so both failed there on every call. The unit test stopped
 * at the refusals.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_layout_builder')]
final class LayoutBlockKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'layout_discovery',
    'layout_builder',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_layout_builder',
  ];

  /**
   * A block is added to a section, saved, and removed again.
   */
  public function testAddAndRemoveBlock(): void {
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'filter', 'node', 'mcp_tools']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $service = $this->container->get('mcp_tools_layout_builder.layout_builder');

    $result = $service->enableLayoutBuilder('node', 'page');
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $result = $service->addSection('node', 'page', 'layout_onecol', 0);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));

    $result = $service->addBlock('node', 'page', 0, 'content', 'system_powered_by_block');
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $uuid = $result['data']['component_uuid'];
    $this->assertArrayHasKey($uuid, $this->savedSection()->getComponents(), 'The block is in the saved layout.');

    $result = $service->removeBlock('node', 'page', $uuid);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertArrayNotHasKey($uuid, $this->savedSection()->getComponents(), 'The block is gone from the saved layout.');
  }

  /**
   * The first section of the display, loaded fresh from storage.
   */
  private function savedSection(): object {
    $storage = $this->container->get('entity_type.manager')->getStorage('entity_view_display');
    $storage->resetCache();
    $display = $storage->load('node.page.default');
    $this->assertInstanceOf(LayoutBuilderEntityViewDisplay::class, $display);
    return $display->getSection(0);
  }

}
