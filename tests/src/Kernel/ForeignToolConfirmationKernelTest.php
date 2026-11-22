<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\mcp_tools\Traits\ForeignToolTestTrait;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\Tests\mcp_tools\Traits\SdkSuspensionTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Request\ElicitRequest;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

/**
 * Another module's destructive tool runs after the client's user said yes.
 *
 * MCP Tools asks for a confirmation before one tool of its own. A Tool API
 * tool of another module that declares itself destructive ran without a
 * question. Where the client can ask its user, the server now asks first.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ForeignToolConfirmationKernelTest extends KernelTestBase {

  use SdkSuspensionTrait;
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
    // The delete tool clears this flag: set means "it did not run".
    $this->container->get('state')->set('foreign_tool_test.wrote', TRUE);
  }

  /**
   * Whether the destructive test tool has not run.
   */
  private function flagIsStillThere(): bool {
    return (bool) $this->container->get('state')->get('foreign_tool_test.wrote', FALSE);
  }

  /**
   * Starts a call in a fiber, as the SDK does.
   *
   * @return array{0: \Fiber, 1: object|null}
   *   The fiber, and the request the server wants the client to answer, or
   *   NULL when the call finished without asking.
   */
  private function start(string $tool, bool $clientCanAsk, array $scopes = ['read', 'write', 'admin']): array {
    $this->container->get('mcp_tools.access_manager')->setScopes($scopes);
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
    $session->method('get')->willReturnCallback(
      static fn(string $key, mixed $default = NULL) => $key === 'client_capabilities'
        ? ($clientCanAsk ? ['elicitation' => []] : [])
        : $default,
    );
    $session->method('getId')->willReturn(Uuid::v4());
    $request = (new CallToolRequest($tool, []))->withId(1);

    $fiber = new \Fiber(static fn() => $handler->handle($request, $session));
    $value = $fiber->start();
    while (!$fiber->isTerminated() && $this->suspendedRequest($value) === NULL) {
      $value = $fiber->resume();
    }
    return [$fiber, $fiber->isTerminated() ? NULL : $this->suspendedRequest($value)];
  }

  /**
   * Gives the client's answer and runs the call to its end.
   */
  private function answer(\Fiber $fiber, array $answer): Response {
    $fiber->resume(new Response(1, $answer));
    while (!$fiber->isTerminated()) {
      $fiber->resume();
    }
    return $fiber->getReturn();
  }

  /**
   * The user is asked, and a yes runs the tool.
   */
  public function testAskedAndAccepted(): void {
    [$fiber, $asked] = $this->start('foreign_test_delete', TRUE);

    $this->assertInstanceOf(ElicitRequest::class, $asked, 'The server asks before it runs the tool.');
    $this->assertStringContainsString('foreign_test_delete', $asked->message);
    $this->assertStringContainsString('foreign_tool_test', $asked->message);
    $this->assertTrue($this->flagIsStillThere(), 'Nothing ran before the answer.');

    $response = $this->answer($fiber, ['action' => 'accept', 'content' => ['confirm' => TRUE]]);
    $this->assertTrue($response->result->structuredContent['success']);
    $this->assertFalse($this->flagIsStillThere(), 'The tool ran after the yes.');
    $this->assertCount(1, $this->auditLines());
  }

  /**
   * A no runs nothing, counts for nothing and is not an error.
   */
  public function testDeclined(): void {
    $this->config('mcp_tools.settings')
      ->set('rate_limiting.enabled', TRUE)
      ->set('rate_limiting.max_deletes_per_hour', 1)
      ->save();

    foreach ([['action' => 'decline'], ['action' => 'cancel'], ['action' => 'accept', 'content' => ['confirm' => FALSE]]] as $answer) {
      [$fiber, $asked] = $this->start('foreign_test_delete', TRUE);
      $this->assertInstanceOf(ElicitRequest::class, $asked);
      $response = $this->answer($fiber, $answer);

      $this->assertFalse($response->result->structuredContent['success']);
      $this->assertStringContainsString('Nothing was run', $response->result->structuredContent['message']);
      $this->assertFalse($response->result->isError, 'A no is a final answer, not an error to retry.');
      $this->assertTrue($this->flagIsStillThere());
    }
    $this->assertSame([], $this->auditLines(), 'What did not run is not in the audit log.');

    // None of the three used up the one delete this hour allows.
    [$fiber, $asked] = $this->start('foreign_test_delete', TRUE);
    $response = $this->answer($fiber, ['action' => 'accept', 'content' => ['confirm' => TRUE]]);
    $this->assertTrue($response->result->structuredContent['success'], (string) $response->result->structuredContent['message']);
  }

  /**
   * Only a tool that declares itself destructive is asked about.
   */
  public function testUndeclaredWriteToolIsNotAskedAbout(): void {
    $this->container->get('state')->delete('foreign_tool_test.wrote');

    [$fiber, $asked] = $this->start('foreign_test_write', TRUE);
    $this->assertNull($asked, 'A plain write tool runs without a question.');
    $this->assertTrue($fiber->getReturn()->result->structuredContent['success']);
    $this->assertTrue($this->flagIsStillThere(), 'The write tool set the flag.');
  }

  /**
   * A new site insists: a client that cannot ask its user is refused.
   */
  public function testANewSiteRefusesAClientThatCannotAsk(): void {
    $this->assertTrue($this->config('mcp_tools.settings')->get('access.confirm_destructive_tools'), 'A new site does not insist on a yes.');
    [$fiber, $asked] = $this->start('foreign_test_delete', FALSE);
    $this->assertNull($asked);
    $result = $fiber->getReturn()->result;
    $this->assertTrue($result->isError);
    $this->assertSame('CONFIRMATION_REQUIRED', $result->structuredContent['error_code']);
    // A model that is refused tries again with "confirm": true, which the
    // tool does not take. The refusal says that no argument changes it.
    $this->assertStringContainsString('do not call it again', (string) $result->content[0]->text);
    $this->assertTrue($this->flagIsStillThere(), 'The tool ran.');
  }

  /**
   * On a site that does not insist, such a client gets the tool run.
   *
   * That is a site from before 1.0.0-beta22, or one that switched it off.
   */
  public function testClientThatCannotAsk(): void {
    $this->config('mcp_tools.settings')->set('access.confirm_destructive_tools', FALSE)->save();
    [$fiber, $asked] = $this->start('foreign_test_delete', FALSE);
    $this->assertNull($asked);
    $this->assertTrue($fiber->getReturn()->result->structuredContent['success']);
    $this->assertFalse($this->flagIsStillThere(), 'The tool ran.');
  }

  /**
   * A site can insist: then a client that cannot ask is refused.
   */
  public function testSiteThatInsistsOnConfirmation(): void {
    $this->config('mcp_tools.settings')->set('access.confirm_destructive_tools', TRUE)->save();

    [$fiber, $asked] = $this->start('foreign_test_delete', FALSE);
    $this->assertNull($asked);
    $result = $fiber->getReturn()->result;
    $this->assertTrue($result->isError);
    $this->assertStringContainsString('cannot ask', $result->structuredContent['message']);
    $this->assertTrue($this->flagIsStillThere(), 'The tool did not run.');

    // A client that can ask is asked, as without the setting.
    [$fiber, $asked] = $this->start('foreign_test_delete', TRUE);
    $this->assertInstanceOf(ElicitRequest::class, $asked);
    $this->answer($fiber, ['action' => 'accept', 'content' => ['confirm' => TRUE]]);
    $this->assertFalse($this->flagIsStillThere());
  }

  /**
   * A session that may not run the tool is refused, and its user not asked.
   */
  public function testNoQuestionWithoutTheWriteScope(): void {
    [$fiber, $asked] = $this->start('foreign_test_delete', TRUE, ['read']);
    $this->assertNull($asked, 'The user of a read-only session is not asked.');
    $result = $fiber->getReturn()->result;
    $this->assertTrue($result->isError);
    $this->assertStringContainsString('Access denied', (string) $result->content[0]->text);
    $this->assertTrue($this->flagIsStillThere());
  }

}
