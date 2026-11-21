<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_moderation\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Listing content by moderation state, on a real workflow.
 *
 * The tool filtered a node query on "moderation_state". That is a computed
 * field no query can filter on, so the tool failed with "'moderation_state'
 * not found" on every site that has a workflow. The unit test stopped at the
 * refusals.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_moderation')]
final class ContentByStateKernelTest extends KernelTestBase {

  use ContentModerationTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'workflows',
    'content_moderation',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_moderation',
  ];

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
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'article');
    $this->setUpCurrentUser([], [], TRUE);
  }

  /**
   * Content is listed under the state of its newest revision.
   */
  public function testContentIsListedUnderTheStateOfItsNewestRevision(): void {
    $draft = Node::create(['type' => 'article', 'title' => 'Draft', 'moderation_state' => 'draft']);
    $draft->save();
    $published = Node::create(['type' => 'article', 'title' => 'Published', 'moderation_state' => 'published']);
    $published->save();
    // Published, with a newer draft waiting.
    $pending = Node::create(['type' => 'article', 'title' => 'Pending', 'moderation_state' => 'published']);
    $pending->save();
    $pending->set('moderation_state', 'draft');
    $pending->setTitle('Pending, as drafted');
    $pending->setNewRevision(TRUE);
    $pending->save();
    // Not moderated: in no state.
    Node::create(['type' => 'page', 'title' => 'Plain'])->save();

    $service = $this->container->get('mcp_tools_moderation.moderation');

    $result = $service->getContentByState('editorial', 'draft');
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertSame(2, $result['data']['total']);
    $ids = array_map('intval', array_column($result['data']['content'], 'entity_id'));
    sort($ids);
    $this->assertSame([(int) $draft->id(), (int) $pending->id()], $ids);
    // The entry shows the draft that is waiting, not the published text.
    $labels = array_column($result['data']['content'], 'label', 'entity_id');
    $this->assertSame('Pending, as drafted', $labels[$pending->id()]);

    $result = $service->getContentByState('editorial', 'published');
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertSame(1, $result['data']['total']);
    $this->assertSame((int) $published->id(), (int) $result['data']['content'][0]['entity_id']);

    $result = $service->getContentByState('editorial', 'archived');
    $this->assertSame(0, $result['data']['total']);
    $this->assertSame([], $result['data']['content']);
  }

}
