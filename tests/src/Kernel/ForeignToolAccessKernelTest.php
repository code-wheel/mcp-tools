<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;

/**
 * Other modules' tools answer to MCP Tools scopes and modes.
 *
 * With "all tools" on, the server also runs Tool API tools of other
 * providers. Scopes, read-only mode and config-only mode were checked inside
 * the MCP Tools tool base class only, so such a tool skipped all three: a
 * read-only key could run another module's write tool.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ForeignToolAccessKernelTest extends KernelTestBase {

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
    'foreign_tool_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    // A write tool of another module that ran leaves an audit line.
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['mcp_tools']);
    $this->setUpCurrentUser();
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
  private function call(string $tool, array $scopes): array {
    $this->container->get('mcp_tools.access_manager')->setScopes($scopes);
    $handler = new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      TRUE,
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

    $response = $handler->handle((new CallToolRequest($tool, []))->withId(1), $session);
    $text = (string) ($response->result->content[0]->text ?? '');

    return [
      'denied' => str_contains($text, 'Access denied'),
      'error' => (bool) $response->result->isError,
      'text' => $text,
    ];
  }

  /**
   * Whether the foreign write tool ran.
   */
  private function wrote(): bool {
    $state = $this->container->get('state');
    $state->resetCache();
    return (bool) $state->get('foreign_tool_test.wrote', FALSE);
  }

  /**
   * A write tool of another module needs the write scope.
   */
  public function testForeignWriteToolNeedsTheWriteScope(): void {
    $result = $this->call('foreign_test_write', ['read']);
    $this->assertTrue($result['denied'], $result['text']);
    $this->assertFalse($this->wrote(), 'The tool did not run.');

    $result = $this->call('foreign_test_write', ['read', 'write']);
    $this->assertFalse($result['error'], $result['text']);
    $this->assertTrue($this->wrote(), 'With the write scope it runs.');
  }

  /**
   * Read-only mode stops another module's write tool.
   */
  public function testReadOnlyModeStopsForeignWriteTools(): void {
    $this->config('mcp_tools.settings')->set('access.read_only_mode', TRUE)->save();

    $result = $this->call('foreign_test_write', ['read', 'write', 'admin']);
    $this->assertTrue($result['denied'], $result['text']);
    $this->assertFalse($this->wrote());
  }

  /**
   * Config-only mode stops another module's write tool.
   *
   * Nothing says what such a tool writes, so it is not let through as a
   * configuration change.
   */
  public function testConfigOnlyModeStopsForeignWriteTools(): void {
    $this->config('mcp_tools.settings')->set('access.config_only_mode', TRUE)->save();

    $result = $this->call('foreign_test_write', ['read', 'write', 'admin']);
    $this->assertTrue($result['denied'], $result['text']);
    $this->assertFalse($this->wrote());
  }

  /**
   * A read tool of another module runs with the read scope, not without it.
   */
  public function testForeignReadToolNeedsTheReadScope(): void {
    $result = $this->call('foreign_test_read', ['read']);
    $this->assertFalse($result['error'], $result['text']);

    $result = $this->call('foreign_test_read', ['write']);
    $this->assertTrue($result['denied'], $result['text']);
  }

  /**
   * The access check reads settings another process just saved.
   *
   * In the long-running STDIO server the check ran before the per-call
   * reset: a mode switched on elsewhere took effect one call late.
   */
  public function testAccessCheckSeesSettingsSavedElsewhere(): void {
    $result = $this->call('foreign_test_write', ['read', 'write']);
    $this->assertFalse($result['error'], $result['text']);
    $this->container->get('state')->delete('foreign_tool_test.wrote');

    // Another process turns read-only mode on: it writes the storage and
    // never touches the copy this process keeps in its config factory.
    $storage = $this->container->get('config.storage');
    $data = $storage->read('mcp_tools.settings');
    $data['access']['read_only_mode'] = TRUE;
    $storage->write('mcp_tools.settings', $data);

    $result = $this->call('foreign_test_write', ['read', 'write']);
    $this->assertTrue($result['denied'], 'The very next call is refused: ' . $result['text']);
    $this->assertFalse($this->wrote());
  }

}
