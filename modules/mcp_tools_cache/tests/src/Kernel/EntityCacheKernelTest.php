<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_cache\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Clearing an entity's cache uses its own tags, and needs the entity.
 *
 * An entity that did not exist was "cleared", and a config entity got a
 * tag no cache carries ("user_role:editor" for config:user.role.editor).
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_cache')]
final class EntityCacheKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_cache'];

  /**
   * The tags are the entity's, and a missing one is refused.
   */
  public function testTheEntitysOwnTags(): void {
    $this->installEntitySchema('user');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'mcp_tools']);
    \Drupal\user\Entity\Role::create(['id' => 'editor', 'label' => 'Editor'])->save();
    $service = $this->container->get('mcp_tools_cache.cache_service');

    $missing = $service->clearEntityCache('node', 999999);
    $this->assertFalse($missing['success'], 'A cache was "cleared" for an entity that does not exist.');

    $role = $service->clearEntityCache('user_role', 'editor');
    $this->assertTrue($role['success'], (string) ($role['error'] ?? ''));
    $this->assertContains('config:user.role.editor', $role['invalidated_tags'], 'The config entity did not get its own tag.');
  }

}
