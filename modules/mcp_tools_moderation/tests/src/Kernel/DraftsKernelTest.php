<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_moderation\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * A draft waiting over published content is what the tools work on.
 *
 * The tools loaded the published revision: "publish" answered "already
 * published" and left the draft, an edit went on top of the published text
 * and buried the draft, and the publish tool said "published" while
 * Content Moderation kept the node a draft.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_moderation')]
final class DraftsKernelTest extends KernelTestBase {

  use ContentModerationTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'filter', 'text', 'node', 'workflows', 'content_moderation', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_content', 'mcp_tools_moderation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'filter', 'node', 'content_moderation', 'mcp_tools']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'article');
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * A published article with a newer draft waiting.
   */
  private function pending(): Node {
    $node = Node::create(['type' => 'article', 'title' => 'Published text', 'moderation_state' => 'published']);
    $node->save();
    $node->setTitle('Draft text');
    $node->set('moderation_state', 'draft');
    $node->setNewRevision(TRUE);
    $node->save();
    return $node;
  }

  /**
   * The published revision, fresh.
   */
  private function published(int $nid): Node {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$nid]);
    return $storage->load($nid);
  }

  /**
   * Publishing publishes the draft.
   */
  public function testPublishingPublishesTheDraft(): void {
    $node = $this->pending();
    $result = $this->container->get('mcp_tools_moderation.moderation')->setModerationState('node', (int) $node->id(), 'published');
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertSame('Draft text', $this->published((int) $node->id())->label(), 'The draft was not published: ' . ($result['data']['message'] ?? ''));
  }

  /**
   * An edit goes into the draft, and the published text stays.
   */
  public function testAnEditGoesIntoTheDraft(): void {
    $node = $this->pending();
    $content = $this->container->get('mcp_tools_content.content');
    $this->assertTrue($content->updateContent((int) $node->id(), ['title' => 'Draft text, edited'])['success']);
    $this->assertSame('Published text', $this->published((int) $node->id())->label(), 'An edit changed the published text.');
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $latest = $storage->loadRevision($storage->getLatestRevisionId($node->id()));
    $this->assertSame('Draft text, edited', $latest->label(), 'The edit did not go into the draft.');
    $this->assertSame('draft', $latest->get('moderation_state')->value);

    $publish = $content->setPublishStatus((int) $node->id(), TRUE);
    $this->assertFalse($publish['success'], 'The publish tool said done on moderated content.');
    $this->assertSame('MODERATED', $publish['code']);
  }

  /**
   * The answer to an edit of a draft says it is the draft, with the live title.
   *
   * It gave the draft's title only. A model that had found the content by
   * its live title then "corrected" the title back, undoing the draft.
   */
  public function testAnEditOfADraftSaysSo(): void {
    $node = $this->pending();
    $result = $this->container->get('mcp_tools_content.content')->updateContent((int) $node->id(), ['title' => 'Draft text, more']);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertTrue($result['data']['draft'] ?? FALSE);
    $this->assertSame('Draft text, more', $result['data']['title']);
    $this->assertSame('Published text', $result['data']['live_title']);
    $this->assertStringContainsString("the live one stays 'Published text'", $result['data']['message']);

    // Content with no draft waiting says nothing of drafts.
    $plain = Node::create(['type' => 'article', 'title' => 'Only', 'moderation_state' => 'published']);
    $plain->save();
    $result = $this->container->get('mcp_tools_content.content')->updateContent((int) $plain->id(), ['title' => 'Only, edited']);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertArrayNotHasKey('draft', $result['data']);
  }

  /**
   * Content made on a moderated type takes no status: its state decides.
   *
   * Content Moderation keeps the status field for the workflow. A status
   * given was refused as a field the user may not set, which said nothing
   * of the workflow.
   */
  public function testNewModeratedContentTakesNoStatus(): void {
    $content = $this->container->get('mcp_tools_content.content');
    $refused = $content->createContent('article', 'With a status', [], ['status' => FALSE]);
    $this->assertFalse($refused['success']);
    $this->assertSame('MODERATED', $refused['code'] ?? NULL, json_encode($refused));
    $this->assertStringContainsString('mcp_moderation_set_state', $refused['error']);
    $made = $content->createContent('article', 'Without one');
    $this->assertTrue($made['success'], json_encode($made));
  }

}
