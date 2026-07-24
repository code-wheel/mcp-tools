<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Executes content tool plugins and reads their typed output values.
 *
 * Regression coverage for conditional outputs: Tool API's getOutputValues()
 * throws for declared-but-unset outputs, which broke external consumers
 * (e.g. mcp_server_tool_bridge) after the tool had already executed.
 * McpToolsToolBase backfills unset declared outputs with NULL.
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_content')]
class ContentToolOutputValuesTest extends KernelTestBase {

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

    $this->setUpCurrentUser();
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * A confirmed full delete exposes all declared outputs without throwing.
   */
  public function testDeleteContentPluginOutputValues(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Doomed', 'langcode' => 'en']);
    $node->save();

    $tool = $this->container->get('plugin.manager.tool')->createInstance('mcp_delete_content');
    $tool->setInputValue('nid', (int) $node->id());
    $tool->setInputValue('confirm_delete_all', TRUE);
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());

    $values = $tool->getOutputValues();
    $this->assertNull($values['language'], 'The single-translation output is NULL on a full delete.');
    $this->assertSame(['en'], $values['languages_deleted']);
    $this->assertSame([], $values['remaining_languages']);
  }

  /**
   * A source-language update exposes all declared outputs without throwing.
   */
  public function testUpdateContentPluginOutputValues(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Before', 'langcode' => 'en']);
    $node->save();

    $tool = $this->container->get('plugin.manager.tool')->createInstance('mcp_update_content');
    $tool->setInputValue('nid', (int) $node->id());
    $tool->setInputValue('updates', ['title' => 'After']);
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());

    $values = $tool->getOutputValues();
    $this->assertSame('After', $values['title']);
    $this->assertSame('en', $values['language']);
    $this->assertNull($values['paragraph_fields_updated'], 'Translate-only output is NULL on a source-language update.');
    $this->assertNull($values['untouched_translatable_fields'], 'Translate-only output is NULL on a source-language update.');
  }

}
