<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_policy\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\mcp_tools\Traits\ForeignToolTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use GuzzleHttp\Psr7\Response as HttpResponse;
use GuzzleHttp\Psr7\ServerRequest;
use Mcp\Event\ErrorEvent;
use Mcp\Event\ResponseEvent;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MCP Tools' rules in front of MCP Server's HTTP endpoint.
 *
 * MCP Server runs a Tool API tool through its tool bridge. Nothing there
 * knows read-only mode, the scopes or the write limits of MCP Tools, and
 * nothing writes its audit line. This module is a middleware in MCP
 * Server's own stack (the place it documents for such rules) that asks
 * them before the call, and a listener that learns how the call ended.
 *
 * It needs MCP Server and its tool bridge on the site. Where they are not,
 * the test is skipped: the release harness runs it on a site that has them
 * and fails when it was skipped there.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class McpServerPolicyKernelTest extends KernelTestBase {

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
    'serialization',
    'tool',
    'mcp_tools',
    'mcp_tools_cache',
    'mcp_server',
    'mcp_server_tool_bridge',
    'foreign_tool_test',
    'mcp_tools_policy',
  ];

  /**
   * What the next handler in the stack saw.
   *
   * @var string[]
   */
  private array $reachedServer = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    if (!class_exists('Drupal\mcp_server\McpServerFactory') || !class_exists('Drupal\mcp_server_tool_bridge\Entity\McpToolConfig')) {
      $this->markTestSkipped('Needs MCP Server and MCP Server Tool Bridge on the site.');
    }
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['mcp_tools']);
    $this->setUpCurrentUser();
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write', 'admin'])
      // A connection of MCP Server has no key of ours: it gets the default
      // scopes, as MCP Tools' own tools do when they are called through it.
      ->set('access.default_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->set('access.config_only_mode', FALSE)
      ->save();

    // What a site builder does on the bridge's form: expose three tools.
    $storage = $this->container->get('entity_type.manager')->getStorage('mcp_tool_config');
    foreach (['foreign_test_read', 'foreign_test_write', 'mcp_cache_clear_all'] as $tool) {
      $storage->create(['id' => $tool, 'tool_id' => $tool, 'status' => TRUE])->save();
    }
    $this->container->get('plugin.manager.mcp_server.tool')->clearCachedDefinitions();
  }

  /**
   * The name MCP Server gives a bridged tool on the wire.
   */
  private function wireName(string $tool): string {
    foreach ($this->container->get('plugin.manager.mcp_server.tool')->getDefinitions() as $name => $definition) {
      if (($definition->toolId ?? NULL) === $tool) {
        return (string) $name;
      }
    }
    $this->fail("MCP Server does not offer $tool.");
  }

  /**
   * Sends one POST body through the middleware.
   *
   * @return array{status: int, body: mixed, reached: bool}
   *   The HTTP status, the decoded body, and whether MCP Server got it.
   */
  private function post(array|string $payload, string $method = 'POST'): array {
    $json = is_string($payload) ? $payload : (string) json_encode($payload);
    $before = count($this->reachedServer);
    $handler = new class($this->reachedServer) implements RequestHandlerInterface {

      public function __construct(private array &$seen) {}

      /**
       * {@inheritdoc}
       */
      public function handle(ServerRequestInterface $request): ResponseInterface {
        // MCP Server reads the body after every middleware.
        $this->seen[] = (string) $request->getBody();
        return new HttpResponse(202);
      }

    };
    $response = $this->container->get('mcp_tools_policy.mcp_server')
      ->process(new ServerRequest($method, 'http://localhost/mcp', ['Content-Type' => 'application/json'], $json), $handler);
    return [
      'status' => $response->getStatusCode(),
      'body' => json_decode((string) $response->getBody(), TRUE),
      'reached' => count($this->reachedServer) > $before,
    ];
  }

  /**
   * A tools/call message.
   */
  private function call(string $tool, int|string $id = 1, array $arguments = []): array {
    return ['jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => ['name' => $this->wireName($tool), 'arguments' => $arguments]];
  }

  /**
   * Tells the module how a call ended, as the MCP SDK does.
   */
  private function ended(string $tool, int|string $id, bool $isError): void {
    $request = (new CallToolRequest($this->wireName($tool), []))->withId($id);
    $result = new CallToolResult([new TextContent('x')], $isError);
    $this->container->get('event_dispatcher')->dispatch(new ResponseEvent(new Response($id, $result), $request, $this->createMock(SessionInterface::class)));
  }

  /**
   * Read-only mode refuses a write before MCP Server sees it.
   */
  public function testReadOnlyModeRefusesAWrite(): void {
    $this->config('mcp_tools.settings')->set('access.read_only_mode', TRUE)->save();

    $answer = $this->post($this->call('foreign_test_write', 7));
    $this->assertFalse($answer['reached'], 'MCP Server did not get the call.');
    $this->assertSame(200, $answer['status']);
    // A tool result the client's model can read, with the call's id.
    $this->assertSame('2.0', $answer['body']['jsonrpc']);
    $this->assertSame(7, $answer['body']['id']);
    $this->assertTrue($answer['body']['result']['isError']);
    $this->assertSame('Access denied.', $answer['body']['result']['content'][0]['text']);
    $this->assertSame('text', $answer['body']['result']['content'][0]['type']);

    // Reading goes on, and MCP Server gets the body it was sent.
    $read = $this->call('foreign_test_read', 8);
    $answer = $this->post($read);
    $this->assertTrue($answer['reached']);
    $this->assertSame(202, $answer['status']);
    $this->assertSame(json_encode($read), end($this->reachedServer));
  }

  /**
   * Without the write scope a write is refused too.
   */
  public function testTheDefaultScopesDecide(): void {
    $this->config('mcp_tools.settings')->set('access.default_scopes', ['read'])->save();

    $this->assertFalse($this->post($this->call('foreign_test_write'))['reached']);
    $this->assertTrue($this->post($this->call('foreign_test_read'))['reached']);
  }

  /**
   * A write over the limit is refused, with the seconds to wait.
   */
  public function testTheWriteLimit(): void {
    $this->config('mcp_tools.settings')
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_writes_per_minute', 2)
      ->save();

    $this->assertTrue($this->post($this->call('foreign_test_write', 1))['reached']);
    $this->assertTrue($this->post($this->call('foreign_test_write', 2))['reached']);

    $answer = $this->post($this->call('foreign_test_write', 3));
    $this->assertFalse($answer['reached']);
    $this->assertSame(3, $answer['body']['id']);
    $this->assertTrue($answer['body']['result']['isError']);
    $text = $answer['body']['result']['content'][0]['text'];
    $this->assertStringContainsStringIgnoringCase('rate limit', $text);
    $this->assertMatchesRegularExpression('/Try again in \d+ seconds/', $text);

    // Reading is not limited.
    $this->assertTrue($this->post($this->call('foreign_test_read', 4))['reached']);
  }

  /**
   * A write that ran leaves an audit line, with how it ended.
   */
  public function testTheAuditLine(): void {
    $this->post($this->call('foreign_test_read', 1));
    $this->ended('foreign_test_read', 1, FALSE);
    $this->assertSame([], $this->auditLines(), 'A read leaves no line.');

    $this->post($this->call('foreign_test_write', 2, ['title' => 'x', 'password' => 'hunter2']));
    $this->assertSame([], $this->auditLines(), 'No line before the call has ended.');
    $this->ended('foreign_test_write', 2, FALSE);
    $lines = $this->auditLines();
    $this->assertCount(1, $lines, implode("\n", $lines));
    $this->assertStringContainsString('run_write_tool on tool "foreign_test_write"', $lines[0]);
    $this->assertStringContainsString('"title":"x"', $lines[0]);
    $this->assertStringNotContainsString('hunter2', $lines[0]);

    // An answer for a call this module never let in writes nothing.
    $this->ended('foreign_test_write', 99, FALSE);
    $this->assertCount(1, $this->auditLines());
  }

  /**
   * A call that ended in a protocol error did not run: its count comes back.
   */
  public function testAProtocolErrorGivesTheCountBack(): void {
    $this->config('mcp_tools.settings')
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_writes_per_minute', 1)
      ->save();

    $this->assertTrue($this->post($this->call('foreign_test_write', 1))['reached']);
    $request = (new CallToolRequest($this->wireName('foreign_test_write'), []))->withId(1);
    $this->container->get('event_dispatcher')->dispatch(new ErrorEvent(Error::forInternalError('boom', 1), $request, $this->createMock(SessionInterface::class), NULL));

    $this->assertTrue($this->post($this->call('foreign_test_write', 2))['reached'], 'There is room for one write again.');
    $this->assertFalse($this->post($this->call('foreign_test_write', 3))['reached']);
    $this->assertSame([], $this->auditLines());
  }

  /**
   * What is not a call to a bridged tool of another module passes.
   */
  public function testEverythingElsePasses(): void {
    $this->config('mcp_tools.settings')->set('access.read_only_mode', TRUE)->save();

    // Not a POST, not JSON, not a call, a tool the bridge does not know.
    $this->assertTrue($this->post('', 'GET')['reached']);
    $this->assertTrue($this->post('not json')['reached']);
    $this->assertTrue($this->post(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])['reached']);
    $this->assertTrue($this->post(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])['reached']);
    $this->assertTrue($this->post(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'a_tool_of_mcp_server_itself', 'arguments' => []]])['reached']);

    // A tool of MCP Tools carries the rules itself: it refuses in its own
    // access check, so it is not judged here.
    $this->assertTrue($this->post($this->call('mcp_cache_clear_all', 3))['reached']);
  }

  /**
   * Two calls with one ID in a POST each get their audit line.
   *
   * And 1 is not "1": JSON-RPC keeps them apart, so this does too.
   */
  public function testCallsThatShareAnId(): void {
    $this->assertTrue($this->post([
      $this->call('foreign_test_write', 5),
      $this->call('foreign_test_write', 5),
      $this->call('foreign_test_write', '5'),
    ])['reached']);

    $this->ended('foreign_test_write', 5, FALSE);
    $this->ended('foreign_test_write', 5, TRUE);
    $this->ended('foreign_test_write', '5', FALSE);
    $this->assertCount(3, $this->auditLines());

    // There is nothing left to mistake a later answer for.
    $this->ended('foreign_test_write', 5, FALSE);
    $this->ended('foreign_test_write', '5', FALSE);
    $this->assertCount(3, $this->auditLines());
  }

  /**
   * What the MCP SDK does not take for a request is not counted.
   */
  public function testWhatWouldNotRunIsNotCounted(): void {
    $this->config('mcp_tools.settings')
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_writes_per_minute', 1)
      ->save();
    $name = $this->wireName('foreign_test_write');

    foreach ([1.5, TRUE, NULL, ['x']] as $id) {
      $this->assertTrue($this->post(['jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => ['name' => $name]])['reached']);
    }
    // An answer that happens to carry a method, and a name that is no name.
    $this->assertTrue($this->post(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'result' => [], 'params' => ['name' => $name]])['reached']);
    $this->assertTrue($this->post(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => [$name]]])['reached']);

    // None of them used the one write up.
    $this->assertTrue($this->post($this->call('foreign_test_write', 3))['reached']);
    $this->assertFalse($this->post($this->call('foreign_test_write', 4))['reached']);
  }

  /**
   * Several messages in one POST: one refusal stops all of them.
   *
   * MCP Server would run the others, and this module cannot answer for
   * part of a POST. So none runs, every request in it gets an answer, and
   * what was counted for the others is given back.
   */
  public function testABatchWithARefusedCall(): void {
    $this->config('mcp_tools.settings')
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_writes_per_minute', 1)
      ->save();

    $answer = $this->post([
      $this->call('foreign_test_write', 'a'),
      $this->call('foreign_test_write', 'b'),
      ['jsonrpc' => '2.0', 'id' => 'c', 'method' => 'tools/list'],
      ['jsonrpc' => '2.0', 'method' => 'notifications/progress'],
    ]);
    $this->assertFalse($answer['reached']);
    $this->assertSame(200, $answer['status']);
    $byId = array_column($answer['body'], NULL, 'id');
    $this->assertSame(['a', 'b', 'c'], array_keys($byId));
    $this->assertStringContainsStringIgnoringCase('rate limit', $byId['b']['result']['content'][0]['text']);
    $this->assertArrayHasKey('error', $byId['a']);
    $this->assertArrayHasKey('error', $byId['c']);

    // The write that was let in and then not run did not use the limit up.
    $this->assertTrue($this->post($this->call('foreign_test_write', 'd'))['reached']);
  }

}
