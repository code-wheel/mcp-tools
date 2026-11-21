<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\mcp_tools\Traits\ForeignToolTestTrait;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;

/**
 * Other modules' write tools are rate limited and audited like our own.
 *
 * MCP Tools' own tools ask the rate limiter and write the audit log from
 * inside their services. A Tool API tool of another module does neither, so
 * with "all tools" on it could be called without limit and left no trace.
 * The server now does both for it.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ForeignToolPolicyKernelTest extends KernelTestBase {

  use ForeignToolTestTrait;
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
   * Calls a tool through the handler, as the server builds it.
   *
   * @return array{error: bool, text: string, structured: array}
   *   What came back.
   */
  private function call(string $tool, array $scopes = ['read', 'write', 'admin']): array {
    $this->container->get('mcp_tools.access_manager')->setScopes($scopes);
    $handler = new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      TRUE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
      NULL,
      // The site's own dispatcher, as the server passes it: the audit log
      // is written by a subscriber.
      $this->container->get('event_dispatcher'),
      NULL,
      $this->container->get('mcp_tools.access_manager'),
      $this->container->get('mcp_tools.tool_call_context'),
    );
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->willReturnArgument(1);

    $response = $handler->handle((new CallToolRequest($tool, []))->withId(1), $session);

    return [
      'error' => (bool) $response->result->isError,
      'text' => (string) ($response->result->content[0]->text ?? ''),
      'structured' => (array) ($response->result->structuredContent ?? []),
    ];
  }

  /**
   * A write tool of another module stops at the write limit.
   */
  public function testForeignWriteToolIsRateLimited(): void {
    $this->config('mcp_tools.settings')
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_writes_per_minute', 2)
      ->save();

    foreach ([1, 2] as $n) {
      $result = $this->call('foreign_test_write');
      $this->assertFalse($result['error'], "call $n: " . $result['text']);
      $this->assertTrue($this->wroteSinceLastLook(), "call $n ran");
    }

    $result = $this->call('foreign_test_write');
    $this->assertTrue($result['error'], 'The third call is refused: ' . $result['text']);
    $this->assertStringContainsStringIgnoringCase('rate limit', $result['text']);
    $this->assertFalse($this->wroteSinceLastLook(), 'The tool did not run.');
    // The refusal tells a client when to come back.
    $this->assertGreaterThan(0, (int) ($result['structured']['retry_after'] ?? 0));

    // Reading is not counted.
    foreach ([1, 2, 3, 4] as $n) {
      $this->assertFalse($this->call('foreign_test_read')['error'], "read $n");
    }
  }

  /**
   * Only a tool that declares itself destructive counts as a delete.
   *
   * Tool API treats a write tool that declares nothing as destructive, as
   * MCP clients do. Going by that, every write tool of another module would
   * be held to the limit for deletes.
   */
  public function testOnlyDeclaredDestructiveToolsCountAsDeletes(): void {
    $this->config('mcp_tools.settings')
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_deletes_per_hour', 1)
      ->save();

    foreach ([1, 2, 3] as $n) {
      $result = $this->call('foreign_test_write');
      $this->assertFalse($result['error'], "plain write $n: " . $result['text']);
    }

    $this->assertFalse($this->call('foreign_test_delete')['error'], 'The first delete runs.');
    $result = $this->call('foreign_test_delete');
    $this->assertTrue($result['error'], 'The second delete is refused: ' . $result['text']);
    $this->assertStringContainsString('delete operations per hour', $result['text']);
  }

  /**
   * A call that is refused for its scope does not use up the limit.
   */
  public function testRefusedCallsAreNotCounted(): void {
    $this->config('mcp_tools.settings')
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_writes_per_minute', 1)
      ->save();

    foreach ([1, 2, 3] as $n) {
      $this->assertTrue($this->call('foreign_test_write', ['read'])['error'], "refused $n");
    }
    $result = $this->call('foreign_test_write');
    $this->assertFalse($result['error'], $result['text']);
    $this->assertTrue($this->wroteSinceLastLook());
  }

  /**
   * A write by another module's tool is in the audit log, a read is not.
   */
  public function testForeignWriteToolIsAudited(): void {
    $this->assertFalse($this->call('foreign_test_read')['error']);
    $this->assertSame([], $this->auditLines(), 'A read leaves no audit line.');

    $result = $this->call('foreign_test_write');
    $this->assertFalse($result['error'], $result['text']);

    $lines = $this->auditLines();
    $this->assertCount(1, $lines, implode("\n", $lines));
    $this->assertStringContainsString('run_write_tool on tool "foreign_test_write"', $lines[0]);
    $this->assertStringContainsString('"provider":"foreign_tool_test"', $lines[0]);
    $this->assertStringContainsString('scopes: read,write,admin', $lines[0]);
  }

  /**
   * With audit logging switched off, nothing is written.
   */
  public function testNoAuditLineWhenAuditLoggingIsOff(): void {
    $this->config('mcp_tools.settings')->set('access.audit_logging', FALSE)->save();

    $this->assertFalse($this->call('foreign_test_write')['error']);
    $this->assertSame([], $this->auditLines());
  }

}
