<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;

/**
 * The call handler: Fiber suspensions, input upcasting, stray arguments.
 *
 * These tests used tools made on the spot and a stand-in for the tool
 * manager. Tool API's runner only runs tools its own manager knows, so the
 * tools are real plugins of the test module now.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ToolApiCallToolHandlerKernelTest extends KernelTestBase {

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
  }

  /**
   * Builds the handler around the site's tools, without the access manager.
   */
  private function buildHandler(): ToolApiCallToolHandler {
    return new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      TRUE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );
  }

  /**
   * Drupal's own Fiber suspensions stay inside the handler.
   *
   * The SDK runs the handler in a Fiber and logs every suspension that is
   * not one of its own as a warning. Drupal core suspends the current Fiber
   * too (entity loads, path alias lookups), so the handler resumes those
   * itself and passes only the SDK's suspensions up.
   */
  public function testCoreFiberSuspensionsDoNotReachTheTransport(): void {
    $handler = $this->buildHandler();
    $request = (new CallToolRequest('foreign_test_suspends', []))->withId(1);
    $session = $this->createMock(SessionInterface::class);

    // Play the transport: record what the handler suspends with.
    $fiber = new \Fiber(static fn() => $handler->handle($request, $session));
    $seen = [];
    $value = $fiber->start();
    while (!$fiber->isTerminated()) {
      $seen[] = $value;
      $value = $fiber->resume('pong');
    }
    $response = $fiber->getReturn();

    $this->assertSame([['type' => 'notification', 'notification' => 'ping']], $seen);
    $this->assertFalse($response->result->isError, (string) $response->result->content[0]->text);
    $this->assertSame('pong', $response->result->structuredContent['data']['answer'], 'The answer to the SDK suspension reached the tool.');
  }

  /**
   * Verifies argument upcasting for Tool API typed inputs.
   */
  public function testArgumentUpcasting(): void {
    $request = (new CallToolRequest('foreign_test_echo', [
      'count' => '5',
      'dry_run' => 'false',
    ]))->withId(1);

    $result = $this->buildHandler()->handle($request, $this->createMock(SessionInterface::class));

    $this->assertInstanceOf(Response::class, $result);
    $this->assertInstanceOf(CallToolResult::class, $result->result);
    $this->assertFalse($result->result->isError, (string) $result->result->content[0]->text);

    $values = $result->result->structuredContent['data']['values'] ?? NULL;
    $this->assertIsArray($values);
    $this->assertSame(5, $values['count'] ?? NULL);
    $this->assertSame(FALSE, $values['dry_run'] ?? NULL);
  }

  /**
   * Undeclared arguments fail validation instead of being silently dropped.
   *
   * End-to-end regression for the incident where an undeclared "language"
   * key on a delete call was discarded and the call fell through to a
   * destructive full-node default (#3612659).
   */
  public function testUnknownArgumentIsRejected(): void {
    $request = (new CallToolRequest('foreign_test_echo', [
      'count' => 5,
      'language' => 'it',
    ]))->withId(2);
    $result = $this->buildHandler()->handle($request, $this->createMock(SessionInterface::class));

    $this->assertInstanceOf(Response::class, $result);
    $this->assertInstanceOf(CallToolResult::class, $result->result);
    $this->assertTrue($result->result->isError);
    $this->assertStringContainsString('language', (string) $result->result->content[0]->text);
  }

  /**
   * Tools without declared inputs also reject stray arguments.
   */
  public function testZeroInputToolRejectsStrayArguments(): void {
    $handler = $this->buildHandler();
    $session = $this->createMock(SessionInterface::class);

    $ok = $handler->handle((new CallToolRequest('foreign_test_read', []))->withId(3), $session);
    $this->assertInstanceOf(Response::class, $ok);
    $this->assertFalse($ok->result->isError, (string) $ok->result->content[0]->text);

    $bad = $handler->handle((new CallToolRequest('foreign_test_read', ['bogus' => TRUE]))->withId(4), $session);
    $this->assertInstanceOf(Response::class, $bad);
    $this->assertTrue($bad->result->isError);
  }

}
