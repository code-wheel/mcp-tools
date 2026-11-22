<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use CodeWheel\McpEvents\ToolExecutionStartedEvent;
use CodeWheel\McpEvents\ToolExecutionSucceededEvent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

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
    'mcp_tools_cache',
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
   * A name with only "tools_" left out runs the tool.
   *
   * A local model called mcp_search_content right after it was shown
   * mcp_tools_search_content, and got "Unknown tool. Did you mean ...?".
   * That cost a call each time. No other tool can be meant.
   */
  public function testANameWithoutToolsRunsTheTool(): void {
    $session = $this->createMock(SessionInterface::class);
    $handler = $this->buildHandler();

    // The call gets the tool's own checks. This user may not use the tool:
    // the short name is refused with the words the full name is.
    $this->setUpCurrentUser();
    $refusedFull = $handler->handle((new CallToolRequest('mcp_tools_get_site_status', []))->withId(5), $session);
    $refusedShort = $handler->handle((new CallToolRequest('mcp_get_site_status', []))->withId(6), $session);
    $this->assertInstanceOf(Response::class, $refusedShort, $refusedShort instanceof Error ? $refusedShort->message : '');
    $this->assertTrue($refusedFull->result->isError);
    $this->assertTrue($refusedShort->result->isError);
    $this->assertSame((string) $refusedFull->result->content[0]->text, (string) $refusedShort->result->content[0]->text);

    $this->setUpCurrentUser([], ['mcp_tools use site_health']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read']);
    $short = $handler->handle((new CallToolRequest('mcp_get_site_status', []))->withId(1), $session);
    $this->assertInstanceOf(Response::class, $short, $short instanceof Error ? $short->message : '');
    $this->assertFalse($short->result->isError, (string) $short->result->content[0]->text);
    $this->assertSame(\Drupal::VERSION, $short->result->structuredContent['data']['drupal_version']);

    // It is the same call as the one by the full name.
    $full = $handler->handle((new CallToolRequest('mcp_tools_get_site_status', []))->withId(2), $session);
    $this->assertSame($full->result->structuredContent['data']['drupal_version'], $short->result->structuredContent['data']['drupal_version']);

    // Its inputs are checked as the tool's own.
    $stray = $handler->handle((new CallToolRequest('mcp_get_site_status', ['no_such_input' => 1]))->withId(3), $session);
    $this->assertInstanceOf(Response::class, $stray);
    $this->assertTrue($stray->result->isError);
    $this->assertStringContainsString('no_such_input', (string) $stray->result->content[0]->text);

    // The other way round: "tools_" put in where the name has none.
    $this->setUpCurrentUser([], ['mcp_tools use site_health', 'mcp_tools use cache']);
    $extra = $handler->handle((new CallToolRequest('mcp_tools_cache_get_status', []))->withId(8), $session);
    $this->assertInstanceOf(Response::class, $extra, $extra instanceof Error ? $extra->message : '');
    $this->assertFalse($extra->result->isError, (string) $extra->result->content[0]->text);

    // A name with nothing behind it stays unknown.
    $none = $handler->handle((new CallToolRequest('mcp_no_such_thing', []))->withId(4), $session);
    $this->assertInstanceOf(Error::class, $none);
    $this->assertSame(Error::METHOD_NOT_FOUND, $none->code);

    // What the site records of the call names the real tool: the events
    // that logs and limits listen to.
    $seen = [];
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(ToolExecutionStartedEvent::class, static function (ToolExecutionStartedEvent $event) use (&$seen): void {
      $seen[] = 'started ' . $event->toolName . ' ' . $event->pluginId;
    });
    $dispatcher->addListener(ToolExecutionSucceededEvent::class, static function (ToolExecutionSucceededEvent $event) use (&$seen): void {
      $seen[] = 'succeeded ' . $event->toolName;
    });
    $recorded = new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      TRUE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
      NULL,
      $dispatcher,
    );
    $recorded->handle((new CallToolRequest('mcp_get_site_status', []))->withId(7), $session);
    $this->assertSame(['started mcp_tools_get_site_status mcp_tools_get_site_status', 'succeeded mcp_tools_get_site_status'], $seen);
  }

  /**
   * The two file tools say so on a site without the File module.
   *
   * They asked for the storage of an entity type that is not there, and
   * the answer was the exception's text.
   */
  public function testTheFileToolsOnASiteWithoutTheFileModule(): void {
    $this->assertFalse($this->container->get('module_handler')->moduleExists('file'));
    $this->setUpCurrentUser([], ['mcp_tools use content', 'mcp_tools use site_health']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read']);
    $session = $this->createMock(SessionInterface::class);

    foreach (['mcp_tools_get_files', 'mcp_tools_find_orphaned_files'] as $tool) {
      $response = $this->buildHandler()->handle((new CallToolRequest($tool, []))->withId(1), $session);
      $this->assertInstanceOf(Response::class, $response);
      $text = (string) $response->result->content[0]->text;
      $this->assertTrue($response->result->isError, $tool . ': ' . $text);
      $this->assertStringContainsString('File module is not enabled', $text, $tool);
      $this->assertStringNotContainsString('entity type does not exist', $text, $tool);
    }
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
