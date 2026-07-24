<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools_content\Service\ContentService;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests that a confirmed full delete is trash-aware.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ContentService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_content')]
class ContentServiceTrashDeleteTest extends KernelTestBase {

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
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_content',
    'trash',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'mcp_tools']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    // Enable trash for nodes (mirrors this project's production setup) and
    // rebuild the container so the trash storage handlers take over.
    $this->config('trash.settings')->set('enabled_entity_types', ['node' => []])->save();
    $this->container->get('kernel')->rebuildContainer();

    $this->setUpCurrentUser();
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * A confirmed delete soft-deletes and says the node is restorable.
   */
  public function testConfirmedDeleteMovesToTrash(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Trashed', 'langcode' => 'en']);
    $node->save();
    $nid = (int) $node->id();

    /** @var \Drupal\mcp_tools_content\Service\ContentService $service */
    $service = $this->container->get('mcp_tools_content.content');
    $result = $service->deleteContent($nid, NULL, TRUE);

    $this->assertTrue($result['success'], $result['error'] ?? '');
    $this->assertStringContainsString('moved to the trash and can be restored', $result['data']['message']);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$nid]);
    $this->assertNull($storage->load($nid), 'The node is hidden from normal loading.');

    $trashed = $this->container->get('trash.manager')->executeInTrashContext(
      'ignore',
      static fn () => $storage->load($nid),
    );
    $this->assertNotNull($trashed, 'The node is recoverable from the trash.');
  }

}
