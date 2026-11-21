<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiGateway;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;

/**
 * The server runs tools through Tool API's runner.
 *
 * It used to drive each tool by hand: make it, set its inputs, ask it for
 * access, execute it. Tool API has one sequence for that, the runner, and is
 * taking the by-hand methods away before its 1.0.0. A tool run by hand is
 * also not told who called it.
 *
 * The result shape and the "Access denied" answer were unit tests with a
 * stand-in tool manager. The runner only runs tools its own manager knows,
 * and neither can be stood in for (both are final), so they are here, with
 * real tools.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ToolRunnerKernelTest extends KernelTestBase {

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
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write', 'admin']);
  }

  /**
   * Calls a tool through the handler, as the server builds it.
   */
  private function call(string $tool, array $arguments = []): CallToolResult {
    $handler = new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      TRUE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
      NULL,
      $this->container->get('event_dispatcher'),
      NULL,
      $this->container->get('mcp_tools.access_manager'),
      $this->container->get('mcp_tools.tool_call_context'),
    );
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->willReturnArgument(1);

    $response = $handler->handle((new CallToolRequest($tool, $arguments))->withId(7), $session);
    $this->assertInstanceOf(Response::class, $response);
    $this->assertSame(7, $response->id);
    return $response->result;
  }

  /**
   * A tool is told that MCP Tools called it.
   *
   * Tool API lets a tool decide by its caller, for one that must not run
   * for callers outside the site. A tool run without an invoker looks like
   * code of the site itself.
   */
  public function testToolLearnsWhoCalledIt(): void {
    $result = $this->call('foreign_test_whoami');

    $this->assertFalse($result->isError, (string) $result->content[0]->text);
    $this->assertSame('mcp_tools', $result->structuredContent['data']['caller']);
  }

  /**
   * What comes back: success, the message, and the outputs under "data".
   */
  public function testResultShape(): void {
    $result = $this->call('foreign_test_whoami');

    $this->assertInstanceOf(CallToolResult::class, $result);
    $this->assertFalse($result->isError);
    $this->assertSame(TRUE, $result->structuredContent['success'] ?? NULL);
    $this->assertSame('Called by mcp_tools.', $result->structuredContent['message'] ?? NULL);
    $this->assertSame(['caller' => 'mcp_tools'], $result->structuredContent['data'] ?? NULL);
    $this->assertStringStartsWith('Called by mcp_tools.', (string) $result->content[0]->text);
  }

  /**
   * A tool whose own access check refuses is answered "Access denied".
   */
  public function testToolThatRefusesAccess(): void {
    $result = $this->call('foreign_test_forbidden');

    $this->assertInstanceOf(CallToolResult::class, $result);
    $this->assertTrue($result->isError);
    $this->assertSame('Access denied.', $result->structuredContent['error'] ?? NULL);
    $this->assertFalse((bool) $this->container->get('state')->get('foreign_tool_test.forbidden_ran', FALSE), 'The tool did not run.');
  }

  /**
   * The gateway's tool provider runs a tool through the same door.
   *
   * The provider has an execute() of its own, for code that uses the
   * gateway library's classes. Our server does not call it. It ran a tool
   * without its inputs and without asking for access, and called that a
   * success.
   */
  public function testGatewayProviderExecutesThroughTheSameDoor(): void {
    $provider = (new ToolApiGateway(
      $this->container->get('plugin.manager.tool'),
      new ToolApiSchemaConverter(),
      new NullLogger(),
      TRUE,
      'mcp_tools',
      NULL,
      $this->container->get('event_dispatcher'),
      $this->container->get('mcp_tools.access_manager'),
      $this->container->get('mcp_tools.tool_call_context'),
    ))->getToolProvider();
    $state = $this->container->get('state');

    // The tool's own access check is asked.
    $result = $provider->execute('foreign_test_forbidden', []);
    $this->assertFalse($result->success, $result->message);
    $this->assertTrue($result->isError);
    $this->assertFalse((bool) $state->get('foreign_tool_test.forbidden_ran', FALSE), 'The tool did not run.');

    // The inputs reach the tool, and its outputs come back.
    $result = $provider->execute('foreign_test_echo', ['count' => 5]);
    $this->assertTrue($result->success, $result->message);
    $this->assertSame(5, $result->data['values']['count'] ?? NULL);

    // The site's rules hold here too.
    $this->config('mcp_tools.settings')->set('access.read_only_mode', TRUE)->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write', 'admin']);
    $result = $provider->execute('foreign_test_write', []);
    $this->assertFalse($result->success, $result->message);
    $this->assertFalse((bool) $state->get('foreign_tool_test.wrote', FALSE), 'Read-only mode stopped the write.');
  }

  /**
   * A call the tool itself refuses does not use up the write limit.
   */
  public function testRefusedCallGivesTheLimitBack(): void {
    $this->config('mcp_tools.settings')
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_writes_per_minute', 1)
      ->save();

    foreach ([1, 2, 3] as $n) {
      $this->assertTrue($this->call('foreign_test_forbidden')->isError, "refused $n");
    }
    $result = $this->call('foreign_test_write');
    $this->assertFalse($result->isError, 'The one write this minute allows still runs: ' . $result->content[0]->text);

    // And then the limit holds.
    $result = $this->call('foreign_test_write');
    $this->assertTrue($result->isError);
    $this->assertStringContainsStringIgnoringCase('rate limit', (string) $result->content[0]->text);
  }

}
