<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;

/**
 * The five old category permissions are gone, from the list and the roles.
 *
 * "site_tools", "content_tools", "config_tools", "user_tools" and
 * "structure_tools" never let anything through. A role that still holds
 * one after the update could not be saved: Drupal refuses a role with a
 * permission nobody defines.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class OldCategoryPermissionsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_cache'];

  /**
   * The update takes them from a role, and the role saves again.
   */
  public function testTheUpdateRevokesThem(): void {
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user', 'mcp_tools']);
    $permissions = array_keys($this->container->get('user.permissions')->getPermissions());
    foreach (['site_tools', 'content_tools', 'config_tools', 'user_tools', 'structure_tools'] as $old) {
      $this->assertNotContains("mcp_tools use $old", $permissions, "The old permission $old is still defined.");
    }

    // A role as an earlier release left it: the old names and a real one.
    Role::create(['id' => 'mcp_executor', 'label' => 'MCP Executor'])->save();
    $this->config('user.role.mcp_executor')->set('permissions', ['mcp_tools use site_tools', 'mcp_tools use site_health', 'mcp_tools use user_tools'])->save();

    $this->container->get('module_handler')->loadInclude('mcp_tools', 'install');
    $message = mcp_tools_update_10004();
    $this->assertStringContainsString('mcp_executor', $message);
    $this->container->get('entity_type.manager')->getStorage('user_role')->resetCache();
    $role = Role::load('mcp_executor');
    $this->assertSame(['mcp_tools use site_health'], $role->getPermissions());
    // It saves: no permission in it that nobody defines.
    $role->save();
    $this->assertSame('No role held the five old MCP Tools category permissions.', mcp_tools_update_10004());
  }

  /**
   * A submodule owns its permission: kept while it is on, gone with it.
   *
   * The base module defined every category's permission, so uninstalling a
   * submodule left its permission on the roles, and the page listed
   * permissions of submodules that were off.
   */
  public function testASubmoduleOwnsItsPermission(): void {
    $this->installEntitySchema('user');
    // The uninstall below writes to the log and clears the module's user data.
    $this->installSchema('dblog', ['watchdog']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'user', 'mcp_tools']);
    $permissions = $this->container->get('user.permissions')->getPermissions();
    $this->assertSame('mcp_tools_cache', $permissions['mcp_tools use cache']['provider'] ?? NULL);
    $this->assertArrayNotHasKey('mcp_tools use metatag', $permissions, 'A submodule that is off has its permission listed.');
    $this->assertArrayNotHasKey('mcp_tools use workflows', $permissions);

    // A role as an earlier release left it.
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();
    $this->config('user.role.editor')->set('permissions', ['mcp_tools use cache', 'mcp_tools use metatag', 'mcp_tools use workflows', 'mcp_tools use site_health'])->save();
    $this->container->get('module_handler')->loadInclude('mcp_tools', 'install');
    $message = mcp_tools_update_10005();
    $this->assertStringContainsString('mcp_tools use metatag', $message);
    $storage = $this->container->get('entity_type.manager')->getStorage('user_role');
    $storage->resetCache();
    $role = $storage->load('editor');
    $this->assertSame(['mcp_tools use cache', 'mcp_tools use site_health'], $role->getPermissions());
    $this->assertContains('mcp_tools_cache', $role->getDependencies()['module'] ?? [], 'The role does not depend on the submodule that gives its permission.');

    // Uninstalling the submodule takes its permission from the role.
    $this->container->get('module_installer')->uninstall(['mcp_tools_cache']);
    $storage = $this->container->get('entity_type.manager')->getStorage('user_role');
    $storage->resetCache();
    $this->assertSame(['mcp_tools use site_health'], $storage->load('editor')->getPermissions());
  }

}
