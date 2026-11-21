<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_metatag\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools_metatag\Service\MetatagService;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests reading and writing meta tags on a real metatag field.
 *
 * @see https://git.drupalcode.org/project/mcp_tools/-/work_items/3613060
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\CoversClass(MetatagService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_metatag')]
class MetatagServiceKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'token',
    'metatag',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_metatag',
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
    $this->installConfig(['system', 'field', 'node', 'metatag', 'mcp_tools']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    // A field name the old name guess did not know, so only a lookup by
    // field type finds it. Metatag's own computed `metatag` field exists on
    // nodes too.
    FieldStorageConfig::create([
      'field_name' => 'field_seo',
      'entity_type' => 'node',
      'type' => 'metatag',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_seo',
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();

    $this->setUpCurrentUser([], [], TRUE);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * Setting a tag stores it and keeps the tags already there.
   */
  public function testSetEntityMetatagsWritesAndMerges(): void {
    $node = Node::create([
      'type' => 'page',
      'title' => 'Page',
      'field_seo' => ['value' => metatag_data_encode(['description' => 'Old description'])],
    ]);
    $node->save();
    $nid = (int) $node->id();

    /** @var \Drupal\mcp_tools_metatag\Service\MetatagService $service */
    $service = $this->container->get('mcp_tools_metatag.metatag');
    $result = $service->setEntityMetatags('node', $nid, ['title' => 'New title']);
    $this->assertTrue($result['success'], $result['error'] ?? '');

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$nid]);
    $stored = metatag_data_decode($storage->load($nid)->get('field_seo')->value);

    $this->assertSame('New title', $stored['title'] ?? NULL);
    $this->assertSame('Old description', $stored['description'] ?? NULL);
  }

  /**
   * Reading returns the stored tags decoded, from the real field.
   */
  public function testGetEntityMetatagsDecodesStoredTags(): void {
    $node = Node::create([
      'type' => 'page',
      'title' => 'Page',
      'field_seo' => ['value' => metatag_data_encode(['description' => 'Stored'])],
    ]);
    $node->save();

    $result = $this->container->get('mcp_tools_metatag.metatag')
      ->getEntityMetatags('node', (int) $node->id());

    $this->assertTrue($result['success'], $result['error'] ?? '');
    $this->assertSame('field_seo', $result['data']['metatag_field']);
    $this->assertSame(['description' => 'Stored'], $result['data']['stored_tags']);
  }

}
