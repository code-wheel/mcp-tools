<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_batch\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Mcp\Schema\Notification\ProgressNotification;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Protocol;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

/**
 * Progress notifications during bulk operations.
 *
 * Drives the SDK Fiber protocol: when the client attached a progressToken,
 * the batch tools emit ProgressNotification suspensions per processed item.
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_batch')]
class BatchProgressNotificationKernelTest extends KernelTestBase {

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
    'mcp_tools_batch',
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

    $this->setUpCurrentUser([], ['mcp_tools use batch']);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * A progress-requesting client receives per-item notifications.
   */
  public function testBulkCreateEmitsProgressNotifications(): void {
    $handler = new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
      NULL,
      NULL,
      $this->container->get('mcp_tools.client_bridge'),
    );

    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->willReturnCallback(
      static fn(string $key, mixed $default = NULL) => match ($key) {
        Protocol::SESSION_ACTIVE_REQUEST_META => ['progressToken' => 'tok-1'],
        'client_capabilities' => [],
        default => $default,
      },
    );
    $session->method('getId')->willReturn(Uuid::v4());

    $request = (new CallToolRequest('mcp_batch_create_content', [
      'content_type' => 'article',
      'items' => [
        ['title' => 'One'],
        ['title' => 'Two'],
        ['title' => 'Three'],
      ],
    ]))->withId(1);

    $notifications = [];
    $fiber = new \Fiber(static fn() => $handler->handle($request, $session));
    $value = $fiber->start();
    while (!$fiber->isTerminated()) {
      if (is_array($value) && ($value['type'] ?? '') === 'notification') {
        $notifications[] = $value['notification'];
      }
      $value = $fiber->resume();
    }
    $response = $fiber->getReturn();

    $this->assertTrue($response->result->structuredContent['success'], $response->result->structuredContent['message'] ?? '');

    $progress = array_values(array_filter($notifications, static fn($n) => $n instanceof ProgressNotification));
    $this->assertCount(3, $progress, 'One progress notification per item.');
    $this->assertSame('tok-1', $progress[0]->progressToken);
    $this->assertSame(3.0, $progress[2]->progress);
    $this->assertSame(3.0, $progress[2]->total);
    $this->assertStringContainsString('3 of 3', (string) $progress[2]->message);
  }

  /**
   * Without an MCP request context the batch service stays silent and works.
   */
  public function testBatchServiceWorksOutsideMcp(): void {
    $result = $this->container->get('mcp_tools_batch.batch')->createMultipleContent('article', [
      ['title' => 'Plain'],
    ]);
    $this->assertNotEmpty($result['data']['created'] ?? $result['created'] ?? $result, 'Bulk create works with the bridge disarmed.');
  }

}
