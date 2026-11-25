<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Kernel;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\mcp_tools\Traits\ForeignToolTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * What the role tools refuse, by what a permission or a role is.
 *
 * The permissions that are refused were a list of 19 names. So a session
 * with the write scope could give a role the permissions of MCP Tools
 * itself, its own role among them, and with them the tools its site had
 * not let it use. And it could give out permissions Drupal marks as "give
 * to trusted roles only" that the list did not name. Those now need the
 * admin scope, and the audit log names them. Roles were protected by name:
 * an administrator role could be deleted, "administrator" too.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_structure')]
final class RoleRulesKernelTest extends KernelTestBase {

  use ForeignToolTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_structure'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['mcp_tools']);
    Role::create(['id' => 'agent', 'label' => 'Agent'])->save();
    Role::create(['id' => 'administrator', 'label' => 'Administrator', 'is_admin' => TRUE])->save();
    Role::create(['id' => 'site_owner', 'label' => 'Site owner', 'is_admin' => TRUE])->save();
    // The first user is uid 1 and passes every permission check.
    $this->createUser();
    $this->setUpCurrentUser([], ['mcp_tools use structure']);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * Runs a tool: whether it said yes, and what it said.
   */
  private function callTool(string $tool, array $input): array {
    $plugin = $this->container->get('plugin.manager.tool')->createInstance($tool);
    foreach ($input as $name => $value) {
      $plugin->setInputValue($name, $value);
    }
    $plugin->execute();
    $this->container->get('entity_type.manager')->getStorage('user_role')->resetCache();
    // A tool that worked says what it did in its "message" output.
    $result = $plugin->getResult();
    $message = $result->isSuccess() ? ($plugin->getOutputValues()['message'] ?? '') : $result->getMessage();
    return [$result->isSuccess(), (string) $message];
  }

  /**
   * How loud the last audit line was.
   */
  private function lastSeverity(): int {
    return (int) $this->container->get('database')->select('watchdog', 'w')
      ->fields('w', ['severity'])
      ->condition('type', 'mcp_tools')
      ->orderBy('wid', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * A role does not get MCP Tools' own permissions through MCP.
   */
  public function testMcpToolsPermissionsAreNotGranted(): void {
    $own = array_keys(array_filter(
      $this->container->get('user.permissions')->getPermissions(),
      static fn(array $permission): bool => str_starts_with((string) $permission['provider'], 'mcp_tools'),
    ));
    $this->assertContains('mcp_tools use structure', $own);
    $this->assertGreaterThan(5, count($own));

    foreach ($own as $permission) {
      [$success, $message] = $this->callTool('mcp_structure_grant_permissions', ['role' => 'agent', 'permissions' => [$permission]]);
      $this->assertFalse($success, $permission);
      $this->assertFalse(Role::load('agent')->hasPermission($permission), "The role got \"$permission\" through MCP.");
      $this->assertStringContainsString($permission, $message);
    }

    // Nor with a new role.
    [$success] = $this->callTool('mcp_structure_create_role', ['id' => 'helper', 'label' => 'Helper', 'permissions' => ['mcp_tools use structure']]);
    $this->assertFalse($success);
    $this->assertNull(Role::load('helper'));
  }

  /**
   * The write scope does not grant a permission for trusted roles only.
   */
  public function testRestrictedPermissionsAreNotGranted(): void {
    $all = $this->container->get('user.permissions')->getPermissions();
    // Marked "restrict access" by core, and on no list of ours.
    foreach (['administer account settings', 'administer nodes', 'administer content types'] as $permission) {
      $this->assertNotEmpty($all[$permission]['restrict access'] ?? FALSE, $permission);
      [$success, $message] = $this->callTool('mcp_structure_grant_permissions', ['role' => 'agent', 'permissions' => [$permission]]);
      $this->assertFalse($success, $permission);
      $this->assertFalse(Role::load('agent')->hasPermission($permission), "The role got \"$permission\" through MCP.");
      $this->assertStringContainsString($permission, $message);
      $this->assertStringContainsString('admin scope', $message);
    }

    // What a site builder gives a role every day still goes through.
    foreach (['access content', 'access user profiles'] as $permission) {
      $this->assertEmpty($all[$permission]['restrict access'] ?? FALSE, $permission);
      [$success, $message] = $this->callTool('mcp_structure_grant_permissions', ['role' => 'agent', 'permissions' => [$permission]]);
      $this->assertTrue($success, $message);
      $this->assertTrue(Role::load('agent')->hasPermission($permission));
    }
  }

  /**
   * The admin scope grants one, and the audit log names it.
   */
  public function testTheAdminScopeGrantsARestrictedPermissionAndLogsIt(): void {
    $this->config('mcp_tools.settings')->set('access.allowed_scopes', ['read', 'write', 'admin'])->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write', 'admin']);

    [$success, $message] = $this->callTool('mcp_structure_grant_permissions', ['role' => 'agent', 'permissions' => ['access content', 'administer nodes']]);
    $this->assertTrue($success, $message);
    $this->assertTrue(Role::load('agent')->hasPermission('administer nodes'));
    $this->assertStringContainsString('trusted roles only: administer nodes.', $message);
    $lines = $this->auditLines();
    $this->assertStringContainsString('grant_permissions on user_role "agent"', (string) end($lines));
    $this->assertStringContainsString('"restrict_access":["administer nodes"]', (string) end($lines));
    // No person said yes to it, so the line stands out as a warning.
    $this->assertSame(RfcLogLevel::WARNING, $this->lastSeverity());

    // A new role is no way around the log.
    [$success, $message] = $this->callTool('mcp_structure_create_role', ['id' => 'builder', 'label' => 'Builder', 'permissions' => ['administer content types']]);
    $this->assertTrue($success, $message);
    $this->assertTrue(Role::load('builder')->hasPermission('administer content types'));
    $lines = $this->auditLines();
    $this->assertStringContainsString('create_role on user_role "builder"', (string) end($lines));
    $this->assertStringContainsString('"restrict_access":["administer content types"]', (string) end($lines));

    // A grant without such a permission has no such note.
    [$success, $message] = $this->callTool('mcp_structure_grant_permissions', ['role' => 'agent', 'permissions' => ['access user profiles']]);
    $this->assertTrue($success, $message);
    $this->assertStringNotContainsString('trusted roles only', $message);
    $lines = $this->auditLines();
    $this->assertStringNotContainsString('restrict_access', (string) end($lines));
    $this->assertSame(RfcLogLevel::NOTICE, $this->lastSeverity());

    // The admin scope does not open the list of names, and none of MCP
    // Tools' own permissions, of which some are for trusted roles only too.
    $all = $this->container->get('user.permissions')->getPermissions();
    $own = array_keys(array_filter($all, static fn(array $permission): bool => str_starts_with((string) $permission['provider'], 'mcp_tools')));
    $this->assertNotEmpty(array_filter($own, static fn(string $permission): bool => !empty($all[$permission]['restrict access'])));
    foreach (['administer permissions', 'administer users', ...$own] as $permission) {
      [$success] = $this->callTool('mcp_structure_grant_permissions', ['role' => 'agent', 'permissions' => [$permission]]);
      $this->assertFalse($success, $permission);
      $this->assertFalse(Role::load('agent')->hasPermission($permission), "The role got \"$permission\" through MCP.");
    }
  }

  /**
   * Not for every visitor, and a connection does not raise itself.
   */
  public function testARestrictedPermissionIsNotForEveryoneOrForTheCaller(): void {
    $this->installConfig(['user']);
    $this->config('mcp_tools.settings')->set('access.allowed_scopes', ['read', 'write', 'admin'])->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write', 'admin']);

    // The two roles that are every visitor and every logged-in user.
    foreach (['anonymous', 'authenticated'] as $role) {
      [$success, $message] = $this->callTool('mcp_structure_grant_permissions', ['role' => $role, 'permissions' => ['administer nodes']]);
      $this->assertFalse($success, $role);
      $this->assertFalse(Role::load($role)->hasPermission('administer nodes'), "The role \"$role\" got a permission for trusted roles only through MCP.");
      $this->assertStringContainsString('administer nodes', $message);
      $this->assertStringContainsString($role, $message);
      // An everyday permission still goes to visitors. The connection's own
      // user is a logged-in user, so "authenticated" gets none it lacks.
      [$success, $message] = $this->callTool('mcp_structure_grant_permissions', ['role' => $role, 'permissions' => ['access content']]);
      $this->assertSame($role === 'anonymous', $success, $message);
    }

    // A role the user of this connection has: the connection would raise
    // itself. Another role gets the permission.
    $own = $this->container->get('current_user')->getRoles(TRUE);
    $this->assertCount(1, $own);
    [$success, $message] = $this->callTool('mcp_structure_grant_permissions', ['role' => $own[0], 'permissions' => ['administer nodes']]);
    $this->assertFalse($success);
    $this->assertFalse(Role::load($own[0])->hasPermission('administer nodes'), 'The connection gave its own role a permission for trusted roles only.');
    $this->assertStringContainsString('raise itself', $message);
    [$success, $message] = $this->callTool('mcp_structure_grant_permissions', ['role' => 'agent', 'permissions' => ['administer nodes']]);
    $this->assertTrue($success, $message);

    // A user who has the permission already raises nothing: user 1 here,
    // who passes every permission check.
    $first = User::load(1);
    $first->addRole('agent');
    $first->save();
    $this->setCurrentUser($first);
    [$success, $message] = $this->callTool('mcp_structure_grant_permissions', ['role' => 'agent', 'permissions' => ['administer content types']]);
    $this->assertTrue($success, $message);
    $this->assertTrue(Role::load('agent')->hasPermission('administer content types'));
  }

  /**
   * An administrator role is not deleted, whatever its name.
   */
  public function testAnAdministratorRoleIsNotDeleted(): void {
    foreach (['administrator', 'site_owner'] as $id) {
      [$success] = $this->callTool('mcp_structure_delete_role', ['id' => $id]);
      $this->assertFalse($success, $id);
      $this->assertNotNull(Role::load($id), "The administrator role \"$id\" was deleted.");
    }
    [$success, $message] = $this->callTool('mcp_structure_delete_role', ['id' => 'agent']);
    $this->assertTrue($success, $message);
    $this->assertNull(Role::load('agent'));
  }

}
