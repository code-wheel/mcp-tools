<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\DatabaseCacheTagsChecksum;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\McpToolCallContext;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * A tool call sees what other processes changed since the last call.
 *
 * The STDIO server is one PHP process that lives for a whole client session.
 * Drupal keeps config, loaded entities and cache tag counts in memory for the
 * life of a process, so without a reset at the start of each tool call the
 * server answers from the first values it saw, however long ago. State has
 * its own test (StateFreshnessKernelTest).
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class LongSessionFreshnessKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
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
    $this->installConfig(['system', 'filter', 'node']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
  }

  /**
   * Config saved by another process is read fresh.
   */
  public function testToolCallSeesConfigSavedElsewhere(): void {
    $factory = $this->container->get('config.factory');
    $factory->getEditable('system.site')->set('name', 'Before')->save();
    $this->assertSame('Before', $factory->get('system.site')->get('name'));

    // Another process saves the config. It writes the storage and never
    // touches the copy this process keeps in its config factory.
    $storage = $this->container->get('config.storage');
    $data = $storage->read('system.site');
    $data['name'] = 'After';
    $storage->write('system.site', $data);
    $this->assertSame('Before', $factory->get('system.site')->get('name'), 'This process still holds the old copy.');

    $context = $this->container->get('mcp_tools.tool_call_context');
    $context->enter();
    $this->assertSame('After', $factory->get('system.site')->get('name'));
    $context->leave();
  }

  /**
   * Content saved by another process is loaded fresh.
   */
  public function testToolCallSeesContentSavedElsewhere(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Before']);
    $node->save();
    $nid = (int) $node->id();
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->assertSame('Before', $storage->load($nid)->label());

    // Another process saves the node: the tables change and that process
    // clears the entity cache all processes share. The copy in this
    // process's memory is out of its reach.
    $database = $this->container->get('database');
    foreach (['node_field_data', 'node_field_revision'] as $table) {
      $database->update($table)->fields(['title' => 'After'])->condition('nid', $nid)->execute();
    }
    $this->container->get('cache.entity')->deleteAll();
    $this->assertSame('Before', $storage->load($nid)->label(), 'This process still holds the old copy.');

    $context = $this->container->get('mcp_tools.tool_call_context');
    $context->enter();
    $this->assertSame('After', $storage->load($nid)->label());
    $context->leave();
  }

  /**
   * A field added by another process is known.
   *
   * The server kept the field list it read first. A value for a field added
   * later was then dropped when the content was saved.
   */
  public function testToolCallSeesFieldsAddedElsewhere(): void {
    // A copy of the field manager stands for the long-running server: it
    // keeps the list it read, whatever happens to the real one after.
    $server = clone $this->container->get('entity_field.manager');
    $this->assertArrayNotHasKey('field_note', $server->getFieldDefinitions('node', 'article'));

    // Another process adds the field. Saving it clears the field cache all
    // processes share and that process's own list, not the server's.
    FieldStorageConfig::create(['field_name' => 'field_note', 'entity_type' => 'node', 'type' => 'string'])->save();
    FieldConfig::create(['field_name' => 'field_note', 'entity_type' => 'node', 'bundle' => 'article', 'label' => 'Note'])->save();
    $this->assertArrayNotHasKey('field_note', $server->getFieldDefinitions('node', 'article'), 'The server still holds the old list.');

    $context = new McpToolCallContext(NULL, NULL, NULL, NULL, NULL, $server);
    $context->enter();
    $this->assertArrayHasKey('field_note', $server->getFieldDefinitions('node', 'article'));
    $context->leave();
  }

  /**
   * The context service the server uses is given the field manager.
   *
   * The test above builds its own context. This one takes the service as
   * the container builds it, so a missing argument in the service file
   * shows.
   */
  public function testTheContextServiceResetsTheFieldLists(): void {
    $manager = $this->createMock(EntityFieldManagerInterface::class);
    $calls = [];
    $manager->method('useCaches')->willReturnCallback(static function ($use) use (&$calls): void {
      $calls[] = $use;
    });
    $this->container->set('entity_field.manager', $manager);

    $context = $this->container->get('mcp_tools.tool_call_context');
    $context->enter();
    $context->leave();

    $this->assertSame([FALSE, TRUE], $calls);
  }

  /**
   * Cache tags invalidated by another process are honoured.
   */
  public function testToolCallSeesCacheTagsInvalidatedElsewhere(): void {
    $bin = $this->container->get('cache.backend.database')->get('mcp_tools_test');
    $bin->set('item', 'value', Cache::PERMANENT, ['mcp_tools_test_tag']);
    $this->assertNotFalse($bin->get('item'));

    // Another process invalidates the tag through its own checksum service.
    $other = new DatabaseCacheTagsChecksum($this->container->get('database'));
    $other->invalidateTags(['mcp_tools_test_tag']);
    $this->assertNotFalse($bin->get('item'), 'This process still trusts the tag counts it remembers.');

    $context = $this->container->get('mcp_tools.tool_call_context');
    $context->enter();
    $this->assertFalse($bin->get('item'));
    $context->leave();
  }

  /**
   * A permission taken away by another process is gone on the next call.
   *
   * Core keeps the permissions it worked out for an account in a memory
   * cache of its own. In the long-running STDIO server a permission removed
   * from the user's role elsewhere stayed in force until the server was
   * restarted.
   */
  public function testToolCallSeesPermissionsChangedElsewhere(): void {
    $this->installSchema('user', ['users_data']);
    $rid = $this->createRole(['access content']);
    // The first user is uid 1, who may do everything whatever the roles say.
    $this->createUser();
    $account = $this->createUser();
    $account->addRole($rid);
    $account->save();
    $this->setCurrentUser($account);
    $this->assertTrue($this->container->get('current_user')->hasPermission('access content'));

    // Another process removes the permission from the role: it writes the
    // config, drops the cached copy all processes share and invalidates the
    // role's cache tag through its own checksum service.
    $storage = $this->container->get('config.storage');
    $data = $storage->read('user.role.' . $rid);
    $data['permissions'] = [];
    $storage->write('user.role.' . $rid, $data);
    $this->container->get('cache.config')->delete('user.role.' . $rid);
    $other = new DatabaseCacheTagsChecksum($this->container->get('database'));
    $other->invalidateTags(['config:user.role.' . $rid, 'config:user_role_list']);
    // On a site the permissions cache all processes share is in the
    // database and goes stale with that tag. In a kernel test it lives in
    // memory and knows nothing of the checksum, so it is emptied by hand.
    $this->container->get('cache.access_policy')->deleteAll();
    $this->assertTrue($this->container->get('current_user')->hasPermission('access content'), 'This process still holds the old answer.');

    $context = $this->container->get('mcp_tools.tool_call_context');
    $context->enter();
    $this->assertFalse($this->container->get('current_user')->hasPermission('access content'));
    $context->leave();
  }

  /**
   * A nested call does not reset anything under the call that contains it.
   */
  public function testNestedCallDoesNotResetTheOuterCall(): void {
    $factory = $this->container->get('config.factory');
    $context = $this->container->get('mcp_tools.tool_call_context');

    $context->enter();
    $outer = $factory->get('system.site');
    $context->enter();
    $this->assertSame($outer, $factory->get('system.site'), 'The inner call reuses what the outer call loaded.');
    $context->leave();
    $context->leave();
  }

}
