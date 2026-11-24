<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_batch\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * A tool may do to a node what its user may do in Drupal.
 *
 * The content and batch tools checked the scope and the tool permission
 * only: an editor who may edit their own articles edited anyone's.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_batch')]
final class EntityAccessKernelTest extends KernelTestBase {

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
    $this->createUser([], 'admin');
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * Own articles yes, someone else's no; and the old way where it is off.
   */
  public function testAToolFollowsNodeAccess(): void {
    $other = Node::create(['type' => 'article', 'title' => 'Not mine', 'uid' => 1]);
    $other->save();
    $editor = $this->setUpCurrentUser([], ['access content', 'create article content', 'edit own article content', 'mcp_tools use content', 'mcp_tools use batch']);
    $mine = Node::create(['type' => 'article', 'title' => 'Mine', 'uid' => $editor->id()]);
    $mine->save();
    $content = $this->container->get('mcp_tools_content.content');
    $batch = $this->container->get('mcp_tools_batch.batch');

    $this->assertTrue($content->updateContent((int) $mine->id(), ['title' => 'Mine, changed'])['success']);
    $result = $content->updateContent((int) $other->id(), ['title' => 'Changed by someone who may not']);
    $this->assertFalse($result['success'], 'An editor changed a node they may not edit.');
    $this->assertSame('ACCESS_DENIED', $result['code'] ?? NULL);
    $result = $batch->updateMultipleContent([['id' => $other->id(), 'fields' => ['title' => 'Batch']], ['id' => $mine->id(), 'fields' => ['title' => 'Batch mine']]]);
    $this->assertSame(1, $result['data']['updated_count'] ?? NULL, json_encode($result));
    $this->assertStringContainsString('may not update', $result['data']['errors'][0]['error']);
    $this->assertFalse($content->deleteContent((int) $other->id(), NULL, TRUE)['success'], 'An editor deleted a node they may not delete.');
    $this->container->get('entity_type.manager')->getStorage('node')->resetCache();
    $this->assertSame('Not mine', Node::load($other->id())->label());

    // A site from before 1.0.0-beta23 keeps the tool permission alone.
    $this->config('mcp_tools.settings')->set('access.entity_access', FALSE)->save();
    $this->assertTrue($content->updateContent((int) $other->id(), ['title' => 'Old way'])['success']);
  }

  /**
   * An editor sets no field Drupal keeps for "administer nodes".
   *
   * The owner, the published status, the dates, promote and sticky: the
   * node form shows them only to those who may administer nodes. The tools
   * took them from any writer, so an editor made content owned by user 1.
   */
  public function testAnEditorSetsNoAdministrativeField(): void {
    $editor = $this->setUpCurrentUser([], ['access content', 'create article content', 'edit own article content', 'mcp_tools use content', 'mcp_tools use batch']);
    $content = $this->container->get('mcp_tools_content.content');
    $batch = $this->container->get('mcp_tools_batch.batch');
    $nodes = fn() => (int) $this->container->get('entity_type.manager')->getStorage('node')->getQuery()->accessCheck(FALSE)->count()->execute();

    $result = $batch->createMultipleContent('article', [['title' => 'As user 1', 'uid' => 1]]);
    $this->assertFalse($result['success'], 'An editor made a node owned by user 1: ' . json_encode($result));
    $this->assertStringContainsString('may not set uid', $result['error']);
    $result = $content->createContent('article', 'Published at once', [], ['status' => TRUE]);
    $this->assertFalse($result['success'], 'An editor published a node, which needs "administer nodes".');
    $this->assertSame(0, $nodes());

    // Their own node, made the plain way, then its owner changed.
    $made = $content->createContent('article', 'Mine');
    $this->assertTrue($made['success'], json_encode($made));
    $nid = (int) ($made['data']['nid'] ?? 0);
    $this->assertFalse($content->updateContent($nid, ['uid' => 1])['success'], 'An editor gave their node to user 1.');
    $this->assertSame((int) $editor->id(), (int) Node::load($nid)->getOwnerId());
  }

  /**
   * An owner who is no user is refused, not turned into anonymous.
   *
   * Node::preSave makes an owner it cannot load the anonymous user, so a
   * node given to user 999 was saved as anonymous's, with success.
   */
  public function testAnOwnerWhoIsNoUser(): void {
    $this->setUpCurrentUser([], [], TRUE);
    $result = $this->container->get('mcp_tools_batch.batch')->createMultipleContent('article', [['title' => 'Owned by nobody', 'uid' => 999]]);
    $this->assertFalse($result['success'], 'A node owned by a user who does not exist was made: ' . json_encode($result));
    $this->assertStringContainsString('uid: no user to refer to as 999', $result['error']);
    $this->assertSame(0, (int) $this->container->get('entity_type.manager')->getStorage('node')->getQuery()->accessCheck(FALSE)->count()->execute());
  }

}
