<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_users\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * An administrator role is one by what it is, not by what it is called.
 *
 * The user tools refused the roles named "administrator" and "admin" and
 * protected the users who have them. A site whose administrator role has
 * another name ("site_owner") had no such protection: the role could be
 * given to any user, and its users could be blocked. And the update tool
 * protected user 1 only: it changed the e-mail address, the status and the
 * roles of every other administrator, by any name. A new e-mail address and
 * a password reset is that account.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_users')]
final class AdminAccountsKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_users', 'mcp_tools_batch'];

  /**
   * The users of the tests: a plain one, and two administrators.
   *
   * @var array<string, \Drupal\user\UserInterface>
   */
  private array $users = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['mcp_tools']);
    Role::create(['id' => 'administrator', 'label' => 'Administrator', 'is_admin' => TRUE])->save();
    Role::create(['id' => 'site_owner', 'label' => 'Site owner', 'is_admin' => TRUE])->save();
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();
    // The first user is uid 1 and passes every permission check, so the
    // session runs as another one.
    $this->createUser();
    $this->users['plain'] = $this->createUser([], 'plain');
    $this->users['administrator'] = $this->createUser([], 'boss', FALSE, ['roles' => ['administrator'], 'mail' => 'boss@example.com']);
    $this->users['site_owner'] = $this->createUser([], 'owner', FALSE, ['roles' => ['site_owner'], 'mail' => 'owner@example.com']);
    // The users tools edit other accounts: Drupal asks "administer users"
    // for that (tools follow entity access).
    $this->setUpCurrentUser([], ['mcp_tools use users', 'mcp_tools use batch', 'administer users']);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * Runs a tool and gives back whether it said yes, and the user as stored.
   */
  private function callTool(string $tool, array $input, ?int $uid = NULL): array {
    $plugin = $this->container->get('plugin.manager.tool')->createInstance($tool);
    foreach ($input as $name => $value) {
      $plugin->setInputValue($name, $value);
    }
    $plugin->execute();
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $storage->resetCache();
    // What a caller reads: the tool's own message when it gave one.
    $message = (string) $plugin->getResult()->getMessage();
    if ($plugin->getResult()->isSuccess()) {
      $message = (string) ($plugin->getOutputValues()['message'] ?? $message);
    }
    return [$plugin->getResult()->isSuccess(), $uid === NULL ? NULL : $storage->load($uid), $message];
  }

  /**
   * An administrator role cannot be given to a user, whatever its name.
   */
  public function testAnAdministratorRoleIsNotGivenOut(): void {
    $uid = (int) $this->users['plain']->id();

    [, $user, $message] = $this->callTool('mcp_assign_user_roles', ['uid' => $uid, 'roles' => ['site_owner', 'editor', 'no_such_role']], $uid);
    $this->assertSame(['editor'], $user->getRoles(TRUE), 'The role that is an administrator role by its flag was given out.');
    // And the answer says which roles were left out and why.
    $this->assertMatchesRegularExpression('/administrator role cannot be assigned via MCP: site_owner\./', $message);
    $this->assertMatchesRegularExpression('/no such role: no_such_role\./', $message);

    [, $user] = $this->callTool('mcp_update_user', ['uid' => $uid, 'updates' => ['roles' => ['site_owner', 'administrator', 'editor']]], $uid);
    $this->assertSame(['editor'], $user->getRoles(TRUE));

    $this->callTool('mcp_create_user', ['username' => 'new_one', 'email' => 'new_one@example.com', 'roles' => ['site_owner', 'editor']]);
    $found = $this->container->get('entity_type.manager')->getStorage('user')->loadByProperties(['name' => 'new_one']);
    $this->assertCount(1, $found);
    $created = reset($found);
    $this->assertSame(['editor'], $created->getRoles(TRUE));

    // The batch tool refused the name "administrator" and nothing else.
    foreach (['site_owner', 'administrator'] as $role) {
      [$success, $user] = $this->callTool('mcp_batch_assign_role', ['role' => $role, 'user_ids' => [$uid]], $uid);
      $this->assertFalse($success, $role);
      $this->assertNotContains($role, $user->getRoles(TRUE), $role);
    }
    // A plain role still goes through.
    User::load($uid)->removeRole('editor')->save();
    [$success, $user] = $this->callTool('mcp_batch_assign_role', ['role' => 'editor', 'user_ids' => [$uid]], $uid);
    $this->assertTrue($success);
    $this->assertSame(['editor'], $user->getRoles(TRUE));
  }

  /**
   * An administrator is not changed through MCP, whatever the role's name.
   */
  public function testAnAdministratorIsNotChanged(): void {
    foreach (['administrator' => 'boss@example.com', 'site_owner' => 'owner@example.com'] as $role => $mail) {
      $uid = (int) $this->users[$role]->id();

      // The e-mail address: with it goes the password reset.
      foreach (['email', 'mail'] as $key) {
        [$success, $user] = $this->callTool('mcp_update_user', ['uid' => $uid, 'updates' => [$key => 'taken@example.com']], $uid);
        $this->assertFalse($success, "$role, $key");
        $this->assertSame($mail, $user->getEmail(), "The e-mail address of a user with the role $role was changed.");
      }

      [$success, $user] = $this->callTool('mcp_update_user', ['uid' => $uid, 'updates' => ['status' => FALSE]], $uid);
      $this->assertFalse($success, $role);
      $this->assertTrue($user->isActive(), $role);

      [$success, $user] = $this->callTool('mcp_update_user', ['uid' => $uid, 'updates' => ['roles' => []]], $uid);
      $this->assertFalse($success, $role);
      $this->assertSame([$role], $user->getRoles(TRUE), $role);

      [$success, $user] = $this->callTool('mcp_block_user', ['uid' => $uid], $uid);
      $this->assertFalse($success, $role);
      $this->assertTrue($user->isActive(), "A user with the role $role was blocked.");

      // One that somebody blocked is not switched on again either.
      User::load($uid)->block()->save();
      [$success, $user] = $this->callTool('mcp_activate_user', ['uid' => $uid], $uid);
      $this->assertFalse($success, $role);
      $this->assertFalse($user->isActive(), "A blocked user with the role $role was activated.");
    }
  }

  /**
   * A plain user is updated, also by the key the tool's description names.
   *
   * The description said "mail" and the code read "email": a change sent
   * as described did nothing and was reported as a success.
   */
  public function testAPlainUserIsUpdatedByMailAndByEmail(): void {
    $uid = (int) $this->users['plain']->id();
    foreach (['mail' => 'first@example.com', 'email' => 'second@example.com'] as $key => $address) {
      [$success, $user, $message] = $this->callTool('mcp_update_user', ['uid' => $uid, 'updates' => [$key => $address]], $uid);
      $this->assertTrue($success, $message);
      $this->assertSame($address, $user->getEmail(), "A change sent as \"$key\" was not made.");
    }

    // A field the tool does not change is refused, not reported as changed.
    [$success, $user, $message] = $this->callTool('mcp_update_user', ['uid' => $uid, 'updates' => ['name' => 'renamed']], $uid);
    $this->assertFalse($success);
    $this->assertStringContainsString('name', $message);
    $this->assertSame('plain', $user->getAccountName());
  }

  /**
   * A connection does not give its own user more permissions by a role.
   *
   * A role's own tools refuse to give a permission for trusted roles only
   * to a role the user of the connection has. Without this, the connection
   * made a new role with that permission and took the role.
   */
  public function testAConnectionDoesNotGiveItselfARole(): void {
    $me = (int) $this->container->get('current_user')->id();
    $this->assertNotSame(1, $me);
    Role::create(['id' => 'power', 'label' => 'Power', 'permissions' => ['administer account settings']])->save();
    Role::create(['id' => 'nothing_new', 'label' => 'Nothing new', 'permissions' => ['mcp_tools use users']])->save();

    // The user tool, the update tool and the batch tool: not for itself.
    [$success, $user, $message] = $this->callTool('mcp_assign_user_roles', ['uid' => $me, 'roles' => ['power']], $me);
    $this->assertFalse($user->hasRole('power'), 'The connection gave its own user a role with a permission it did not have.');
    $this->assertStringContainsString('power', $message);
    $this->assertStringContainsString('its own user', $message);

    [$success, $user, $message] = $this->callTool('mcp_update_user', ['uid' => $me, 'updates' => ['roles' => ['power']]], $me);
    $this->assertFalse($success);
    $this->assertFalse($user->hasRole('power'));
    $this->assertStringContainsString('its own user', $message);

    [$success, $user] = $this->callTool('mcp_batch_assign_role', ['role' => 'power', 'user_ids' => [$me, (int) $this->users['plain']->id()]], $me);
    $this->assertFalse($user->hasRole('power'));

    // Nor does another user get it from a connection without Drupal's
    // permission for it (testRolesAboveTheConnectionAreNotGivenToOthers).
    $plain = (int) $this->users['plain']->id();
    $this->assertFalse(User::load($plain)->hasRole('power'));
    [$success, $user] = $this->callTool('mcp_assign_user_roles', ['uid' => $plain, 'roles' => ['editor']], $plain);
    $this->assertTrue($success);
    $this->assertTrue($user->hasRole('editor'));

    // A role that adds no permission raises nothing, so it is assigned.
    [$success, $user, $message] = $this->callTool('mcp_assign_user_roles', ['uid' => $me, 'roles' => ['nothing_new']], $me);
    $this->assertTrue($success, $message);
    $this->assertTrue($user->hasRole('nothing_new'));
  }

  /**
   * A role above the connection's own is not given to any account.
   *
   * The rule looked at the connection's own account only: it could make
   * another one with the role, and a password it chose. Drupal asks
   * "administer permissions" for giving roles.
   */
  public function testRolesAboveTheConnectionAreNotGivenToOthers(): void {
    Role::create(['id' => 'manager', 'label' => 'Manager'])->grantPermission('administer permissions')->save();
    $users = $this->container->get('mcp_tools_users.user');
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $roles = function (int $uid) use ($storage): array {
      $storage->resetCache([$uid]);
      return $storage->load($uid)->getRoles(TRUE);
    };
    $plain = (int) $this->users['plain']->id();

    $made = $users->createUser('puppet', 'puppet@example.com', ['password' => 'chosen-by-caller', 'roles' => ['manager']]);
    $this->assertFalse($made['success']);
    $this->assertStringContainsString('manager', $made['error']);
    $this->assertSame([], $storage->loadByProperties(['name' => 'puppet']), 'No account was made.');

    $this->assertFalse($users->updateUser($plain, ['roles' => ['manager']])['success']);
    $users->assignRoles($plain, ['manager']);
    $this->assertSame([], $roles($plain));
    $this->container->get('mcp_tools_batch.batch')->assignRoleToUsers('manager', [$plain]);
    $this->assertSame([], $roles($plain));

    // A role that adds nothing is given as before.
    $this->assertTrue($users->createUser('writer', 'writer@example.com', ['roles' => ['editor']])['success']);
    $users->assignRoles($plain, ['editor']);
    $this->assertSame(['editor'], $roles($plain));

    // Drupal's permission for it gives roles to others, not to oneself.
    Role::create(['id' => 'strong', 'label' => 'Strong'])->grantPermission('access user profiles')->save();
    $me = $this->setUpCurrentUser([], ['mcp_tools use users', 'mcp_tools use batch', 'administer users', 'administer permissions']);
    // An account it makes is one it can act as: not that one either.
    $this->assertFalse($users->createUser('second', 'second@example.com', ['roles' => ['strong']])['success']);
    $this->container->get('mcp_tools_batch.batch')->assignRoleToUsers('strong', [$plain]);
    $this->assertContains('strong', $roles($plain));
    $this->assertFalse($users->updateUser((int) $me->id(), ['roles' => array_merge($me->getRoles(TRUE), ['strong'])])['success']);
  }

}
