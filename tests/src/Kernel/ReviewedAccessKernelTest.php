<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\Core\Cache\CacheCollector;
use Drupal\Core\Form\FormState;
use Drupal\Core\State\State;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\RateLimiter;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\mcp_tools_remote\Form\RemoteSettingsForm;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * What the security review of 2026-11-24 found stays closed.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ReviewedAccessKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_users', 'mcp_tools_structure', 'mcp_tools_remote', 'mcp_tools_batch'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'user', 'mcp_tools', 'mcp_tools_remote']);
    Role::create(['id' => 'power', 'label' => 'Power'])->grantPermission('administer account settings')->save();
    // The first user is uid 1, who may do everything.
    $this->createUser();
  }

  /**
   * Starts a session of a user with these permissions and scopes.
   */
  private function session(array $permissions, array $scopes = ['read', 'write']): User {
    $me = $this->setUpCurrentUser([], $permissions);
    $this->config('mcp_tools.settings')->set('access.allowed_scopes', $scopes)->set('access.read_only_mode', FALSE)->save();
    $this->container->get('mcp_tools.access_manager')->setScopes($scopes);
    return $me;
  }

  /**
   * An account as it is stored.
   */
  private function stored(int $uid): User {
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $storage->resetCache();
    return $storage->load($uid);
  }

  /**
   * Content tools are not config, and an unknown category is not either.
   */
  public function testConfigOnlyModeHoldsForContentTools(): void {
    $this->config('mcp_tools.settings')->set('access.config_only_mode', TRUE)->set('access.config_only_allowed_write_kinds', ['config'])->save();
    $access = $this->container->get('mcp_tools.access_manager');
    $kinds = [
      'jsonapi\\CreateEntity' => 'content',
      'jsonapi\\UpdateEntity' => 'content',
      'jsonapi\\DeleteEntity' => 'content',
      'metatag\\SetEntityMetatags' => 'content',
      'structure\\SetupTaxonomy' => 'content',
      'structure\\CreateRole' => 'config',
    ];
    foreach ($kinds as $tool => $kind) {
      [$module, $name] = explode('\\', $tool);
      $this->container->get('class_loader')->addPsr4("Drupal\\mcp_tools_$module\\", dirname(__DIR__, 3) . "/modules/mcp_tools_$module/src");
      $is = (new \ReflectionMethod("Drupal\\mcp_tools_$module\\Plugin\\tool\\Tool\\$name", 'getMcpWriteKind'))->invoke(NULL);
      $this->assertSame($kind, $is, $tool);
      $this->assertSame($kind === 'config', $access->isWriteKindAllowed($is), $tool);
    }
    // A category on no list. The base class has "discovery", which is on none.
    $this->assertSame('content', (new \ReflectionMethod(McpToolsToolBase::class, 'getMcpWriteKind'))->invoke(NULL));
  }

  /**
   * No account the connection can act as gets more than its own user has.
   */
  public function testAnAccountTheConnectionMadeIsNotRaised(): void {
    $users = $this->container->get('mcp_tools_users.user');
    $this->session(['administer users', 'administer permissions'], ['read', 'write', 'admin']);
    foreach ([['password' => 'chosen'], []] as $options) {
      $this->assertFalse($users->createUser('puppet', 'p@example.com', $options + ['roles' => ['power']])['success']);
    }
    // Made first, the role after: by each tool that gives roles.
    $made = $users->createUser('puppet', 'p@example.com', ['password' => 'chosen']);
    $this->assertTrue($made['success']);
    $uid = (int) $made['data']['uid'];
    $users->assignRoles($uid, ['power']);
    $this->assertFalse($users->updateUser($uid, ['roles' => ['power']])['success']);
    $this->container->get('mcp_tools_batch.batch')->assignRoleToUsers('power', [$uid]);
    $this->assertSame([], $this->stored($uid)->getRoles(TRUE));

    // An account whose address it changes, before or in the same call.
    $other = (int) $this->createUser([], 'other')->id();
    $this->assertFalse($users->updateUser($other, ['email' => 'mine@example.com', 'roles' => ['power']])['success']);
    $this->assertTrue($users->updateUser($other, ['email' => 'mine@example.com'])['success']);
    $this->assertFalse($users->updateUser($other, ['roles' => ['power']])['success']);

    // Another person's account gets it, as Drupal's permission says.
    $plain = (int) $this->createUser([], 'plain')->id();
    $this->assertTrue($users->updateUser($plain, ['roles' => ['power']])['success']);
    $this->assertTrue($this->stored($plain)->hasRole('power'));
  }

  /**
   * Nor by a role that is empty when it is given and filled after.
   */
  public function testARoleOfSuchAnAccountIsNotFilled(): void {
    $users = $this->container->get('mcp_tools_users.user');
    $roles = $this->container->get('mcp_tools_structure.role');
    $this->session(['administer users'], ['read', 'write', 'admin']);
    $this->assertTrue($roles->createRole('later', 'Later')['success']);
    $made = $users->createUser('puppet', 'p@example.com', ['password' => 'chosen', 'roles' => ['later']]);
    $this->assertTrue($made['success']);
    foreach (['administer account settings', 'access user profiles'] as $permission) {
      $this->assertFalse($roles->grantPermissions('later', [$permission])['success'], $permission);
      $this->assertFalse($this->stored((int) $made['data']['uid'])->hasPermission($permission), $permission);
    }
    // A role no such account has is filled as before.
    $this->assertTrue($roles->createRole('free', 'Free')['success']);
    $this->assertTrue($roles->grantPermissions('free', ['administer account settings', 'access user profiles'])['success']);
  }

  /**
   * A connection does not raise itself by a plain permission either.
   */
  public function testOwnRolesGetNoPlainPermission(): void {
    $roles = $this->container->get('mcp_tools_structure.role');
    $me = $this->session(['administer users']);
    $own = $me->getRoles(TRUE)[0];
    foreach ([$own, 'authenticated'] as $role) {
      $this->assertFalse($roles->grantPermissions($role, ['access user profiles'])['success'], $role);
    }
    $this->assertTrue($roles->grantPermissions('power', ['access user profiles'])['success']);
  }

  /**
   * The address of an account above the connection is not changed.
   */
  public function testTheAddressOfAStrongerAccountStays(): void {
    $target = (int) $this->createUser([], 'target', FALSE, ['roles' => ['power'], 'mail' => 't@example.com'])->id();
    $weak = (int) $this->createUser([], 'weak', FALSE, ['mail' => 'w@example.com'])->id();
    $users = $this->container->get('mcp_tools_users.user');
    foreach ([[TRUE, ['administer users']], [FALSE, []], [TRUE, ['administer users', 'administer permissions']]] as [$entityAccess, $permissions]) {
      $this->session($permissions);
      $this->config('mcp_tools.settings')->set('access.entity_access', $entityAccess)->save();
      $this->assertFalse($users->updateUser($target, ['email' => 'taken@example.com'])['success']);
      $this->assertSame('t@example.com', $this->stored($target)->getEmail());
      $this->assertTrue($users->updateUser($weak, ['email' => 'new@example.com'])['success']);
    }
  }

  /**
   * A blocked uid 1 is not switched on again.
   */
  public function testUidOneIsNotActivated(): void {
    User::load(1)->block()->save();
    $this->session(['administer users']);
    $this->assertFalse($this->container->get('mcp_tools_users.user')->activateUser(1)['success']);
    $this->assertFalse($this->stored(1)->isActive());
  }

  /**
   * Uid 1 runs the tools only when somebody ticked the box.
   */
  public function testTheRemoteFormDoesNotAllowUidOneByItself(): void {
    $this->session(['mcp_tools administer']);
    $errors = function (bool $box, int $uid): array {
      $state = new FormState();
      $state->setValues(['enabled' => TRUE, 'execution_user_wrapper' => ['use_uid1' => $box, 'execution_user' => $uid]]);
      $form = [];
      RemoteSettingsForm::create($this->container)->validateForm($form, $state);
      $found = $state->getErrors();
      $state->clearErrors();
      return $found;
    };
    $this->assertNotEmpty($errors(FALSE, 1));
    $this->assertSame([], $errors(TRUE, 1));
    $this->assertSame([], $errors(FALSE, 2));

    // A site that has uid 1 without the box stored: it is not ticked.
    foreach ([FALSE, TRUE] as $allowed) {
      $this->config('mcp_tools_remote.settings')->set('uid', 1)->set('allow_uid1', $allowed)->save();
      $state = new FormState();
      $form = RemoteSettingsForm::create($this->container)->buildForm([], $state);
      $this->assertSame($allowed, $form['execution_user_wrapper']['use_uid1']['#default_value']);
    }
  }

  /**
   * A message is escaped on a page and is as built for a client.
   */
  public function testAMessageIsNotMarkup(): void {
    $text = 'x <img src=x onerror=alert(1)> "y"';
    $message = (new \ReflectionMethod(McpToolsToolBase::class, 'resultMessage'))->invoke(NULL, $text);
    $this->assertSame($text, McpToolsToolBase::plainMessage($message));
    foreach ([['#markup' => $message], ['#type' => 'inline_template', '#template' => '{{ m }}', '#context' => ['m' => $message]]] as $build) {
      $this->assertStringNotContainsString('<img', (string) $this->container->get('renderer')->renderInIsolation($build));
    }
  }

  /**
   * The audit line keeps no secret, in JSON text neither.
   */
  public function testTheAuditLineKeepsNoSecret(): void {
    $logger = $this->container->get('mcp_tools.audit_logger');
    $sanitize = new \ReflectionMethod($logger, 'sanitizeDetails');
    foreach (['credential', 'hash_salt', 'private_dsn', 'password', 'api_key'] as $key) {
      $this->assertStringNotContainsString('S3CR3T', json_encode($sanitize->invoke($logger, ['arguments' => [$key => 'S3CR3T']])), $key);
    }
    $out = json_encode($sanitize->invoke($logger, ['arguments' => ['config' => '{"password":"S3CR3T","name":"kept"}']]));
    $this->assertStringNotContainsString('S3CR3T', $out);
    $this->assertStringContainsString('kept', $out);
  }

  /**
   * Two calls at once do not both take the last place.
   */
  public function testCountsOfCallsAtOnce(): void {
    $this->config('mcp_tools.settings')->set('rate_limiting.enabled', TRUE)->set('rate_limiting.max_writes_per_minute', 2)->save();
    $this->container->get('request_stack')->getCurrentRequest() ?? $this->container->get('request_stack')->push(Request::create('/'));
    $state = fn() => new State($this->container->get('keyvalue'), $this->container->get('cache.bootstrap'), $this->container->get('lock'));
    $limiter = fn(State $s) => new RateLimiter($this->container->get('config.factory'), $s, $this->container->get('request_stack'), $this->container->get('private_key'), $this->container->get('lock'), $this->container->get('keyvalue'));
    $this->assertInstanceOf(RateLimiter::class, $this->container->get('mcp_tools.rate_limiter'));
    $this->assertNotNull((new \ReflectionProperty(RateLimiter::class, 'keyValue'))->getValue($this->container->get('mcp_tools.rate_limiter')));
    $end = static function (State $s): void {
      if ($s instanceof CacheCollector) {
        $s->destruct();
      }
    };
    $store = $this->container->get('keyvalue')->get('state');
    $first = $state();
    $this->assertTrue($limiter($first)->checkLimit('write')['allowed']);
    $end($first);
    $key = array_values(array_filter(array_keys($store->getAll()), static fn($k) => str_ends_with((string) $k, '.write.minute')))[0];
    $warm = $state();
    $warm->get($key);
    $end($warm);
    // Both requests read the counter before either counts.
    [$a, $b] = [$state(), $state()];
    $a->get($key);
    $b->get($key);
    $this->assertTrue($limiter($a)->checkLimit('write')['allowed']);
    $this->assertFalse($limiter($b)->checkLimit('write')['allowed'], 'Two calls at once both took the last place.');
    $this->assertSame(2, $store->get($key)['count']);
    $limiter($b)->refund('write');
    $this->assertSame(1, $store->get($key)['count']);
  }

}
