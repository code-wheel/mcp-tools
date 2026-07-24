<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Parameterized MCP resource templates (drupal://node/{nid}, config/{name}).
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
class ResourceTemplatesKernelTest extends KernelTestBase {

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
    $this->installConfig(['system', 'field', 'filter', 'node', 'mcp_tools']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->setUpCurrentUser([], ['access content']);
  }

  /**
   * The registry exposes the node and config templates.
   */
  public function testTemplatesAreRegistered(): void {
    $templates = $this->container->get('mcp_tools.resource_registry')->getResourceTemplates();
    $uris = array_column($templates, 'uriTemplate');

    $this->assertContains('drupal://node/{nid}', $uris);
    $this->assertContains('drupal://config/{name}', $uris);
    foreach ($templates as $template) {
      $this->assertIsCallable($template['handler']);
    }
  }

  /**
   * The node template returns a summary and honors missing nodes.
   */
  public function testNodeTemplate(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Resource me',
      'langcode' => 'en',
      'status' => 1,
    ]);
    $node->save();

    $provider = $this->container->get('mcp_tools.resource_provider.core');

    $summary = $provider->readNode((string) $node->id());
    $this->assertSame('Resource me', $summary['title']);
    $this->assertSame('article', $summary['type']);
    $this->assertSame('published', $summary['status']);
    $this->assertSame(['en'], $summary['languages']);

    $missing = $provider->readNode('99999');
    $this->assertArrayHasKey('error', $missing);
  }

  /**
   * The config template reads through the redacting analysis service.
   */
  public function testConfigTemplate(): void {
    $provider = $this->container->get('mcp_tools.resource_provider.core');

    $result = $provider->readConfig('system.site');
    $this->assertSame('system.site', $result['name']);
    $this->assertArrayHasKey('data', $result);

    $missing = $provider->readConfig('no.such.config');
    $this->assertNotEmpty($missing['error'] ?? $missing['success'] === FALSE ? 'err' : '');
  }

}
