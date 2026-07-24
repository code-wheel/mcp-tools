<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Request\ElicitRequest;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

/**
 * Elicitation-based destructive confirmation on mcp_delete_content.
 *
 * Drives the SDK's Fiber suspension protocol directly: a bare delete from an
 * elicitation-capable client suspends with an ElicitRequest; the resumed
 * answer decides whether the delete proceeds.
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_content')]
class ElicitationConfirmationKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'mcp_tools']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->setUpCurrentUser([], ['mcp_tools use content']);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * Pumps the fiber until the SDK's elicit suspension or termination.
   *
   * Drupal core suspends fibers for its own scheduling (FiberResumeType);
   * those are resumed transparently — exactly what the SDK transport loop
   * does in production.
   *
   * @return array|null
   *   The SDK suspension payload, or NULL when the fiber finished first.
   */
  protected function pumpToElicit(\Fiber $fiber): ?array {
    $value = $fiber->start();
    while (!$fiber->isTerminated() && !(is_array($value) && ($value['type'] ?? '') === 'request')) {
      $value = $fiber->resume();
    }
    return $fiber->isTerminated() ? NULL : $value;
  }

  /**
   * Sends the elicitation answer and pumps the fiber to completion.
   */
  protected function finish(\Fiber $fiber, mixed $answer): mixed {
    $fiber->resume($answer);
    while (!$fiber->isTerminated()) {
      $fiber->resume();
    }
    return $fiber->getReturn();
  }

  /**
   * Builds the handler under test.
   */
  protected function buildHandler(): ToolApiCallToolHandler {
    return new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );
  }

  /**
   * Builds a session mock, optionally advertising elicitation support.
   */
  protected function buildSession(bool $elicitation): SessionInterface {
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->willReturnCallback(
      static fn(string $key, mixed $default = NULL) => $key === 'client_capabilities'
        ? ($elicitation ? ['elicitation' => []] : [])
        : $default,
    );
    $session->method('getId')->willReturn(Uuid::v4());
    return $session;
  }

  /**
   * An accepted elicitation confirms and executes the delete.
   */
  public function testAcceptedElicitationDeletes(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Doomed', 'langcode' => 'en']);
    $node->save();
    $nid = (int) $node->id();

    $handler = $this->buildHandler();
    $request = (new CallToolRequest('mcp_delete_content', ['nid' => $nid]))->withId(1);
    $session = $this->buildSession(TRUE);

    $fiber = new \Fiber(static fn() => $handler->handle($request, $session));
    $suspended = $this->pumpToElicit($fiber);

    $this->assertIsArray($suspended, 'The bare delete suspended to ask the client.');
    $this->assertSame('request', $suspended['type']);
    $this->assertInstanceOf(ElicitRequest::class, $suspended['request']);
    $this->assertStringContainsString((string) $nid, $suspended['request']->message);

    $response = $this->finish($fiber, new Response(1, ['action' => 'accept', 'content' => ['confirm' => TRUE]]));

    $this->assertInstanceOf(Response::class, $response);
    $this->assertTrue($response->result->structuredContent['success']);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$nid]);
    $this->assertNull($storage->load($nid), 'The node was deleted after user confirmation.');
  }

  /**
   * A declined elicitation cancels without deleting.
   */
  public function testDeclinedElicitationCancels(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Spared', 'langcode' => 'en']);
    $node->save();
    $nid = (int) $node->id();

    $handler = $this->buildHandler();
    $request = (new CallToolRequest('mcp_delete_content', ['nid' => $nid]))->withId(2);
    $session = $this->buildSession(TRUE);

    $fiber = new \Fiber(static fn() => $handler->handle($request, $session));
    $suspended = $this->pumpToElicit($fiber);
    $this->assertIsArray($suspended);

    $response = $this->finish($fiber, new Response(2, ['action' => 'decline']));

    $this->assertFalse($response->result->structuredContent['success']);
    $this->assertStringContainsString('declined', $response->result->structuredContent['message']);
    $this->assertFalse($response->result->isError, 'A decline is a final answer, not a retryable error.');

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$nid]);
    $this->assertNotNull($storage->load($nid), 'The node survived the declined confirmation.');
  }

  /**
   * Without the elicitation capability the refusal-based guardrail applies.
   */
  public function testWithoutCapabilityFallsBackToRefusal(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Guarded', 'langcode' => 'en']);
    $node->save();
    $nid = (int) $node->id();

    $handler = $this->buildHandler();
    $request = (new CallToolRequest('mcp_delete_content', ['nid' => $nid]))->withId(3);
    $session = $this->buildSession(FALSE);

    $fiber = new \Fiber(static fn() => $handler->handle($request, $session));
    $suspended = $this->pumpToElicit($fiber);

    $this->assertNull($suspended, 'No elicitation was attempted.');
    $response = $fiber->getReturn();
    $this->assertFalse($response->result->structuredContent['success']);
    $this->assertStringContainsString('confirm_delete_all', $response->result->structuredContent['message']);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$nid]);
    $this->assertNotNull($storage->load($nid));
  }

}
