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
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;

/**
 * Tool arguments that hold objects reach the tool as arrays.
 *
 * Up to mcp/sdk 0.9 the SDK decoded every JSON object in the arguments to a
 * PHP array. From 0.10 it keeps nested objects as \stdClass, so that {} and
 * [] stay apart. Tool API inputs and this module's tools work with arrays:
 * without a conversion, an update with {"updates": {"title": "..."}} failed
 * with "Values must be represented as an associative array".
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_content')]
final class NestedArgumentsKernelTest extends KernelTestBase {

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

    $this->setUpCurrentUser([], ['mcp_tools use content', 'bypass node access']);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * An object argument, and objects inside a list, work as arrays do.
   */
  public function testObjectArgumentsReachTheToolAsArrays(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Before']);
    $node->save();
    $nid = (int) $node->id();

    $handler = new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->willReturnArgument(1);

    // The shape mcp/sdk 0.10 hands over: nested JSON objects as \stdClass.
    $request = (new CallToolRequest('mcp_update_content', [
      'nid' => $nid,
      'updates' => (object) ['title' => 'After'],
    ]))->withId(1);
    $response = $handler->handle($request, $session);

    $this->assertInstanceOf(Response::class, $response);
    $text = $response->result->content[0]->text ?? '';
    $this->assertFalse($response->result->isError, $text);
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$nid]);
    $this->assertSame('After', $storage->load($nid)->label());

    // An empty object for an input that is a map is still an object to the
    // validator, not a list.
    $request = (new CallToolRequest('mcp_update_content', [
      'nid' => $nid,
      'updates' => new \stdClass(),
    ]))->withId(2);
    $response = $handler->handle($request, $session);
    $text = $response->result->content[0]->text ?? '';
    $this->assertStringNotContainsString('must match the type', $text, 'An empty object passes validation as an object.');
  }

}
