<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_users\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;

/**
 * Blocking and activating a user are write operations.
 *
 * Both tools were declared as read operations. The server then told clients
 * they change nothing, listed them to read-only sessions, and skipped the
 * config-only rule for them: with that mode on, a session could still block
 * an account.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_users')]
final class UserStatusToolsKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_users',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['mcp_tools']);
    // The first user is uid 1 and passes every permission check, so the
    // session runs as a second one.
    $this->createUser();
    $this->setUpCurrentUser([], ['mcp_tools use users']);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write', 'admin'])
      ->set('access.read_only_mode', FALSE)
      ->set('access.config_only_mode', FALSE)
      ->save();
  }

  /**
   * Calls a tool through the handler as the server builds it.
   *
   * @return array{denied: bool, error: bool, text: string}
   *   What came back.
   */
  private function call(string $tool, array $arguments, array $scopes): array {
    $this->container->get('mcp_tools.access_manager')->setScopes($scopes);
    $handler = new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
      NULL,
      NULL,
      NULL,
      $this->container->get('mcp_tools.access_manager'),
      $this->container->get('mcp_tools.tool_call_context'),
    );
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->willReturnArgument(1);

    $response = $handler->handle((new CallToolRequest($tool, $arguments))->withId(1), $session);
    $text = (string) ($response->result->content[0]->text ?? '');

    return [
      'denied' => str_contains($text, 'Access denied'),
      'error' => (bool) $response->result->isError,
      'text' => $text,
    ];
  }

  /**
   * Whether the account can log in, read from storage.
   */
  private function isActive(int $uid): bool {
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $storage->resetCache([$uid]);
    $user = $storage->load($uid);
    $this->assertInstanceOf(User::class, $user);
    return $user->isActive();
  }

  /**
   * Clients are not told that these tools change nothing.
   */
  public function testClientsAreToldTheToolsWrite(): void {
    $tools = $this->container->get('plugin.manager.tool');
    $converter = new ToolApiSchemaConverter();
    foreach (['mcp_block_user', 'mcp_activate_user'] as $id) {
      $annotations = $converter->toolDefinitionToAnnotations($tools->getDefinition($id));
      $this->assertFalse($annotations['readOnlyHint'], "$id is not read-only.");
    }
  }

  /**
   * A session with the read scope alone is refused before the tool runs.
   */
  public function testReadScopeIsRefused(): void {
    $uid = (int) $this->createUser()->id();

    $result = $this->call('mcp_block_user', ['uid' => $uid], ['read']);
    $this->assertTrue($result['denied'], $result['text']);
    $this->assertTrue($this->isActive($uid));
  }

  /**
   * Config-only mode stops both tools: a user account is content.
   */
  public function testConfigOnlyModeStopsBlockingAndActivating(): void {
    $uid = (int) $this->createUser()->id();
    $this->config('mcp_tools.settings')
      ->set('access.config_only_mode', TRUE)
      ->set('access.config_only_allowed_write_kinds', ['config'])
      ->save();

    $result = $this->call('mcp_block_user', ['uid' => $uid], ['read', 'write']);
    $this->assertTrue($result['denied'], $result['text']);
    $this->assertTrue($this->isActive($uid), 'The account was not blocked.');

    // Without the mode the same call blocks the account, so the refusal
    // above came from the mode.
    $this->config('mcp_tools.settings')->set('access.config_only_mode', FALSE)->save();
    $result = $this->call('mcp_block_user', ['uid' => $uid], ['read', 'write']);
    $this->assertFalse($result['error'], $result['text']);
    $this->assertFalse($this->isActive($uid), 'The account is blocked.');

    $this->config('mcp_tools.settings')->set('access.config_only_mode', TRUE)->save();
    $result = $this->call('mcp_activate_user', ['uid' => $uid], ['read', 'write']);
    $this->assertTrue($result['denied'], $result['text']);
    $this->assertFalse($this->isActive($uid), 'The account was not activated.');

    $this->config('mcp_tools.settings')->set('access.config_only_mode', FALSE)->save();
    $result = $this->call('mcp_activate_user', ['uid' => $uid], ['read', 'write']);
    $this->assertFalse($result['error'], $result['text']);
    $this->assertTrue($this->isActive($uid), 'The account is active again.');
  }

}
