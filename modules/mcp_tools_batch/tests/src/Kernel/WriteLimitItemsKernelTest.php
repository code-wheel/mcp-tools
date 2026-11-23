<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_batch\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * A batch counts each item against the write limits.
 *
 * With three writes a minute allowed, one batch made fifty nodes: a call
 * counted as one write, whatever it held.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_batch')]
final class WriteLimitItemsKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_content', 'mcp_tools_batch'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'filter', 'node', 'mcp_tools']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $this->config('mcp_tools.settings')
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_writes_per_minute', 3)
      ->save();
  }

  /**
   * Items, not calls.
   */
  public function testEachItemCounts(): void {
    $batch = $this->container->get('mcp_tools_batch.batch');
    $nodes = fn() => (int) $this->container->get('entity_type.manager')->getStorage('node')->getQuery()->accessCheck(FALSE)->count()->execute();
    $items = fn(int $n) => array_map(fn($i) => ['title' => "Item $i"], range(1, $n));

    $big = $batch->createMultipleContent('article', $items(5));
    $this->assertFalse($big['success'], 'A batch of five went through a limit of three a minute.');
    $this->assertStringContainsString('at most 3', $big['error']);
    $this->assertSame(0, $nodes());

    $this->assertTrue($batch->createMultipleContent('article', $items(2))['success']);
    $over = $batch->createMultipleContent('article', $items(2));
    $this->assertFalse($over['success'], 'Two more went through with one left.');
    $this->assertStringContainsString('at most 1', $over['error']);
    $this->assertTrue($this->container->get('mcp_tools_content.content')->createContent('article', 'Last one')['success']);
    $this->assertSame(3, $nodes());
  }

}
