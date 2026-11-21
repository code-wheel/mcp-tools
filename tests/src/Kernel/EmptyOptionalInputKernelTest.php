<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\mcp_tools\Traits\DeclaredOutputTypesTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests that an empty string for an optional filter means "no filter".
 *
 * MCP clients often send "" for an optional field instead of leaving it
 * out (MR !5).
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class EmptyOptionalInputKernelTest extends KernelTestBase {

  use DeclaredOutputTypesTrait;

  use UserCreationTrait;

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
    'dblog',
    'update',
    'tool',
    'mcp_tools',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'mcp_tools']);
    $this->setUpCurrentUser([], [], TRUE);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    Node::create(['type' => 'page', 'title' => 'One', 'status' => 1])->save();
  }

  /**
   * Recent content with an empty type filter, and without a sort.
   */
  public function testRecentContentTreatsEmptyFilterAsNone(): void {
    $data = $this->runRecentContent(['content_type' => '']);
    $this->assertSame(1, $data['total']);
    $this->assertSame('changed', $data['sorted_by']);
  }

  /**
   * Runs mcp_tools_get_recent_content and returns its outputs.
   */
  private function runRecentContent(array $input): array {
    $tool = $this->container->get('plugin.manager.tool')->createInstance('mcp_tools_get_recent_content');
    foreach ($input as $name => $value) {
      $tool->setInputValue($name, $value);
    }
    $tool->execute();
    $this->assertTrue($tool->getResult()->isSuccess(), (string) $tool->getResult()->getMessage());
    $this->assertOutputsHaveDeclaredTypes($tool, (string) $tool->getPluginId());
    return $tool->getOutputValues();
  }

}
