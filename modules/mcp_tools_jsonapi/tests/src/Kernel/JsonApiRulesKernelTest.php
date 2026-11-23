<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_jsonapi\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * The JSON:API tools hold to the allowlist, field access and validation.
 *
 * The allowlist was only applied in discovery. Writes took fields a user
 * may not set, skipped fields that do not exist and said "updated", and
 * saved without Drupal's validation. A filter that could not be used was
 * dropped and the answer looked filtered.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_jsonapi')]
final class JsonApiRulesKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'taxonomy', 'file', 'serialization', 'jsonapi', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_jsonapi'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'filter', 'node', 'mcp_tools', 'mcp_tools_jsonapi']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    // User 1 owns everything; the caller is an editor.
    $this->createUser([], 'admin');
    $this->setUpCurrentUser([], ['access content', 'create article content', 'edit any article content']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * The rules, one call each.
   */
  public function testTheRulesHold(): void {
    $service = $this->container->get('mcp_tools_jsonapi.service');
    $node = Node::create(['type' => 'article', 'title' => 'Before', 'uid' => 1]);
    $node->save();

    $this->config('mcp_tools_jsonapi.settings')->set('allowed_entity_types', ['node'])->save();
    $this->assertFalse($service->listEntities('taxonomy_term')['success'], 'A type outside the allowlist was listed.');
    $this->assertTrue($service->listEntities('node')['success']);
    $this->config('mcp_tools_jsonapi.settings')->set('allowed_entity_types', [])->save();

    $result = $service->updateEntity('node', $node->uuid(), ['titel' => 'Typo']);
    $this->assertFalse($result['success'], 'A field the node does not have was skipped and the update said success.');
    $this->assertStringContainsString('titel', $result['error']);

    $result = $service->updateEntity('node', $node->uuid(), ['uid' => 2]);
    $this->assertFalse($result['success'], 'An editor set the owner, a field only administrators may set.');
    $this->assertStringContainsString('uid', $result['error']);

    $result = $service->updateEntity('node', $node->uuid(), ['title' => str_repeat('x', 300)]);
    $this->assertFalse($result['success'], 'A title longer than its field allows was saved.');

    $this->container->get('entity_type.manager')->getStorage('node')->resetCache();
    $this->assertSame('Before', Node::load($node->id())->label(), 'Something was saved all the same.');
    $this->assertTrue($service->updateEntity('node', $node->uuid(), ['title' => 'After'])['success']);

    $this->assertFalse($service->listEntities('node', NULL, ['field_tags.entity.name' => 'x'])['success'], 'A filter that cannot be used was dropped.');
  }

}
