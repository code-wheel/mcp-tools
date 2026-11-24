<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_undo\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;

/**
 * What one tool call changed can be undone, and only when nothing else did.
 *
 * A write tool's change was for good: a wrong title, a deleted page or a
 * changed setting had to be put back by hand, if anyone knew what it was.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_undo')]
final class UndoKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_undo'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('mcp_tool_call');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'filter', 'node', 'mcp_tools', 'mcp_tools_undo']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * Runs $change as one tool call and gives its call id.
   */
  private function call(callable $change): string {
    $context = $this->container->get('mcp_tools.tool_call_context');
    $context->enter();
    try {
      $id = (string) $context->getCorrelationId();
      $change();
    }
    finally {
      $context->leave();
    }
    return $id;
  }

  /**
   * The undo service.
   */
  private function undo(string $callId): array {
    return $this->container->get('mcp_tools_undo.undo')->undo($callId);
  }

  /**
   * A fresh copy of a node.
   */
  private function node(int $nid): ?Node {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$nid]);
    return $storage->load($nid);
  }

  /**
   * Made, changed, deleted: each undone.
   */
  public function testEachKindOfChangeIsUndone(): void {
    $kept = Node::create(['type' => 'page', 'title' => 'Before']);
    $kept->save();
    $gone = Node::create(['type' => 'page', 'title' => 'To be deleted']);
    $gone->save();
    $made = NULL;

    $call = $this->call(function () use ($kept, $gone, &$made) {
      $made = Node::create(['type' => 'page', 'title' => 'Made by the call']);
      $made->save();
      $kept->setTitle('Changed by the call');
      $kept->setNewRevision(TRUE);
      $kept->save();
      $gone->delete();
    });
    $countRevisions = fn() => (int) $this->container->get('entity_type.manager')->getStorage('node')->getQuery()->allRevisions()->accessCheck(FALSE)->condition('nid', $kept->id())->count()->execute();
    $revisions = $countRevisions();

    $result = $this->undo($call);
    $this->assertTrue($result['success'], json_encode($result));
    $this->assertSame(3, $result['data']['undone_count'] ?? NULL, json_encode($result));
    $this->assertNull($this->node((int) $made->id()), 'The node the call made is still there.');
    $this->assertSame('Before', $this->node((int) $kept->id())->label(), 'The change was not undone.');
    $this->assertSame($revisions + 1, $countRevisions(), 'The undo is not a revision of its own.');
    $this->assertSame('To be deleted', $this->node((int) $gone->id())?->label(), 'The deleted node was not made again.');
  }

  /**
   * Config set and config made in a call: undone.
   */
  public function testConfigIsUndone(): void {
    $this->config('system.site')->set('name', 'Before')->save();
    // A config with a schema that is not there yet, for the call to make.
    $this->container->get('config.factory')->getEditable('system.cron')->delete();
    $call = $this->call(function () {
      $this->container->get('config.factory')->getEditable('system.site')->set('name', 'Changed by the call')->save();
      $this->container->get('config.factory')->getEditable('system.cron')->set('logging', TRUE)->save();
    });
    $result = $this->undo($call);
    $this->assertTrue($result['success'], json_encode($result));
    $this->assertSame('Before', $this->config('system.site')->get('name'));
    $this->assertTrue($this->container->get('config.factory')->get('system.cron')->isNew(), 'The config the call made is still there.');
  }

  /**
   * A user, which has no revisions, gets its values back.
   */
  public function testAnEntityWithoutRevisionsGetsItsValuesBack(): void {
    $user = User::create(['name' => 'ann', 'mail' => 'ann@example.com', 'status' => 1]);
    $user->save();
    $call = $this->call(function () use ($user) {
      $user->setEmail('changed@example.com');
      $user->save();
    });
    $this->assertTrue($this->undo($call)['success']);
    $this->assertSame('ann@example.com', User::load($user->id())->getEmail());
  }

  /**
   * A change after the call refuses the undo, and nothing is undone.
   */
  public function testALaterChangeRefusesTheUndo(): void {
    $node = Node::create(['type' => 'page', 'title' => 'Before']);
    $node->save();
    $call = $this->call(function () use ($node) {
      $node->setTitle('By the call');
      $node->setNewRevision(TRUE);
      $node->save();
    });
    // An editor changes it afterwards.
    $later = $this->node((int) $node->id());
    $later->setTitle('By an editor');
    $later->setNewRevision(TRUE);
    $later->save();

    $result = $this->undo($call);
    $this->assertFalse($result['success'], 'The undo overwrote a later change.');
    $this->assertSame('CONFLICT', $result['code'] ?? NULL, json_encode($result));
    $this->assertSame('By an editor', $this->node((int) $node->id())->label());
  }

  /**
   * Saves outside a tool call are not written down, and an unknown call is
   * refused.
   */
  public function testOnlyToolCallsAreWrittenDown(): void {
    $node = Node::create(['type' => 'page', 'title' => 'By hand']);
    $node->save();
    $recent = $this->container->get('mcp_tools_undo.undo')->recent(10);
    $this->assertSame([], $recent['data']['calls'] ?? NULL, json_encode($recent));
    $this->assertFalse($this->undo('no-such-call')['success']);

    $call = $this->call(function () {
      Node::create(['type' => 'page', 'title' => 'By a call'])->save();
    });
    $recent = $this->container->get('mcp_tools_undo.undo')->recent(10);
    $this->assertSame([$call], array_column($recent['data']['calls'], 'call_id'));
    $this->assertStringContainsString('By a call', json_encode($recent['data']['calls'][0]['changes']));
  }

  /**
   * A call is a record people can see, and old ones go on cron.
   */
  public function testACallIsARecordAndOldOnesGo(): void {
    $call = $this->call(function () {
      Node::create(['type' => 'page', 'title' => 'Seen'])->save();
    });
    $storage = $this->container->get('entity_type.manager')->getStorage('mcp_tool_call');
    $records = $storage->loadByProperties(['call_id' => $call]);
    $this->assertCount(1, $records);
    $record = reset($records);
    $this->assertStringContainsString('made node', (string) $record->get('summary')->value);
    $this->assertSame('applied', $record->get('state')->value);

    // A person undoes it from Reports: no MCP scopes there.
    $this->container->get('mcp_tools.access_manager')->setScopes(['read']);
    $this->assertTrue($this->container->get('mcp_tools_undo.undo')->undo($call, TRUE)['success']);
    $storage->resetCache();
    $this->assertSame('undone', $storage->load($record->id())->get('state')->value);
    $this->assertSame('a person', $storage->load($record->id())->get('undone_by')->value);

    // Older than keep_days: removed on cron.
    $this->config('mcp_tools_undo.settings')->set('keep_days', 1)->save();
    $old = $storage->load($record->id());
    $old->set('created', time() - 2 * 86400)->save();
    $this->container->get('cron')->run();
    $storage->resetCache();
    $this->assertNull($storage->load($record->id()), 'A call older than keep_days was kept.');
  }

}
