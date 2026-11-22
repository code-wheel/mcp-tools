<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\mcp_tools\Mcp\GatewayDirectCallHandler;
use Drupal\mcp_tools\Mcp\ToolApiGateway;
use Drupal\Tests\UnitTestCase;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * In gateway mode a tool can be called by its own name.
 *
 * A model that has found a tool often calls it by that name, and not
 * through the gateway's run tool. The server said "Tool not found", and a
 * small model then tried the same call until it ran out of turns.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\GatewayDirectCallHandler::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class GatewayDirectCallHandlerTest extends UnitTestCase {

  private const GATEWAY_TOOLS = [
    ToolApiGateway::DISCOVER_TOOL,
    ToolApiGateway::GET_INFO_TOOL,
    ToolApiGateway::EXECUTE_TOOL,
  ];

  /**
   * The requests the door was given.
   *
   * @var \Mcp\Schema\Request\CallToolRequest[]
   */
  private array $seen = [];

  /**
   * A handler whose door records what it is given.
   */
  private function handler(): GatewayDirectCallHandler {
    $door = $this->createMock(RequestHandlerInterface::class);
    $door->method('handle')->willReturnCallback(function (Request $request): Response {
      $this->seen[] = $request;
      return new Response($request->getId(), new CallToolResult([]));
    });
    return new GatewayDirectCallHandler($door, self::GATEWAY_TOOLS);
  }

  /**
   * The gateway's own three tools stay with the server.
   */
  public function testTheGatewaysOwnToolsAreLeftToTheServer(): void {
    $handler = $this->handler();
    foreach (self::GATEWAY_TOOLS as $name) {
      $this->assertFalse($handler->supports((new CallToolRequest($name, []))->withId(1)), $name);
    }
    $this->assertTrue($handler->supports((new CallToolRequest('mcp_tools_get_site_status', []))->withId(1)));
    $this->assertTrue($handler->supports((new CallToolRequest('mcp_no_such_tool', []))->withId(1)));
    // Only tool calls: a list request is not ours.
    $this->assertFalse($handler->supports((new ListToolsRequest())->withId(1)));
  }

  /**
   * A call by the tool's own name goes to the door as it came.
   */
  public function testADirectCallGoesThroughTheDoor(): void {
    $request = (new CallToolRequest('mcp_tools_get_config', ['name' => 'system.site']))->withId('a');
    $this->handler()->handle($request, $this->createMock(SessionInterface::class));

    $this->assertCount(1, $this->seen);
    $this->assertSame($request, $this->seen[0]);
  }

  /**
   * Arguments in the run tool's shape are unwrapped.
   *
   * A model mixes the two ways: the tool's own name, and the arguments as
   * the run tool takes them.
   */
  public function testArgumentsInTheRunToolsShapeAreUnwrapped(): void {
    $session = $this->createMock(SessionInterface::class);
    $handler = $this->handler();

    $handler->handle((new CallToolRequest('mcp_tools_get_config', [
      'tool_name' => 'mcp_tools_get_config',
      'arguments' => ['name' => 'system.site'],
    ]))->withId('a'), $session);
    $this->assertSame('mcp_tools_get_config', $this->seen[0]->name);
    $this->assertSame(['name' => 'system.site'], $this->seen[0]->arguments);
    $this->assertSame('a', $this->seen[0]->getId());

    // From mcp/sdk 0.10 a nested object arrives as an object. An empty
    // object inside it stays one.
    $inner = new \stdClass();
    $inner->options = new \stdClass();
    $handler->handle((new CallToolRequest('mcp_x', ['tool_name' => 'mcp_x', 'arguments' => $inner]))->withId('b'), $session);
    $this->assertEquals(['options' => new \stdClass()], $this->seen[1]->arguments);

    // The name alone, for a tool without inputs.
    $handler->handle((new CallToolRequest('mcp_x', ['tool_name' => 'mcp_x']))->withId('c'), $session);
    $this->assertSame([], $this->seen[2]->arguments);
  }

  /**
   * A name that does not exist is answered with where to look.
   */
  public function testAnUnknownNameIsAnsweredWithWhereToLook(): void {
    $door = $this->createMock(RequestHandlerInterface::class);
    $door->method('handle')->willReturnCallback(static fn(Request $request): Error => Error::forMethodNotFound('Unknown tool: mcp_list_content_types. Did you mean mcp_tools_list_content_types?', $request->getId()));
    $handler = new GatewayDirectCallHandler($door, self::GATEWAY_TOOLS);

    $response = $handler->handle((new CallToolRequest('mcp_list_content_types', []))->withId('q'), $this->createMock(SessionInterface::class));

    $this->assertInstanceOf(Error::class, $response);
    $this->assertSame(Error::METHOD_NOT_FOUND, $response->code);
    $this->assertSame('q', $response->id);
    $this->assertSame('Unknown tool: mcp_list_content_types. Did you mean mcp_tools_list_content_types? Find tools with mcp_tools_discover_tools.', $response->message);
  }

  /**
   * A name close to one of the gateway's own tools is answered with it.
   *
   * A model called mcp_execute_tool. The closest names came from the
   * catalog only, which does not hold the gateway's three tools, so the
   * answer had no suggestion at all.
   */
  public function testANameCloseToAGatewayToolIsAnsweredWithThatTool(): void {
    $door = $this->createMock(RequestHandlerInterface::class);
    $door->method('handle')->willReturnCallback(static fn(Request $request): Error => Error::forMethodNotFound('Unknown tool: ' . $request->name . '.', $request->getId()));
    $handler = new GatewayDirectCallHandler($door, self::GATEWAY_TOOLS);
    $session = $this->createMock(SessionInterface::class);

    $response = $handler->handle((new CallToolRequest('mcp_execute_tool', []))->withId('q'), $session);
    $this->assertInstanceOf(Error::class, $response);
    $this->assertSame(Error::METHOD_NOT_FOUND, $response->code);
    $this->assertSame('q', $response->id);
    $this->assertSame('Unknown tool: mcp_execute_tool. Did you mean mcp_tools_execute_tool? Find tools with mcp_tools_discover_tools.', $response->message);

    $response = $handler->handle((new CallToolRequest('mcp_discover_tools', []))->withId('r'), $session);
    $this->assertStringContainsString('Did you mean mcp_tools_discover_tools?', $response->message);

    // A name near none of the three keeps the door's answer.
    $response = $handler->handle((new CallToolRequest('mcp_tools_site_health', []))->withId('s'), $session);
    $this->assertSame('Unknown tool: mcp_tools_site_health. Find tools with mcp_tools_discover_tools.', $response->message);
  }

  /**
   * What only looks like the run tool's shape is left alone.
   */
  public function testOtherArgumentsAreLeftAlone(): void {
    $session = $this->createMock(SessionInterface::class);
    $handler = $this->handler();

    // Another tool's name: this tool has an input called "tool_name".
    $other = ['tool_name' => 'mcp_other', 'arguments' => ['a' => 1]];
    $handler->handle((new CallToolRequest('mcp_x', $other))->withId(1), $session);
    $this->assertSame($other, $this->seen[0]->arguments);

    // A third key: not the run tool's shape.
    $more = ['tool_name' => 'mcp_x', 'arguments' => ['a' => 1], 'limit' => 5];
    $handler->handle((new CallToolRequest('mcp_x', $more))->withId(2), $session);
    $this->assertSame($more, $this->seen[1]->arguments);

    // "arguments" that is no object.
    $text = ['tool_name' => 'mcp_x', 'arguments' => 'all'];
    $handler->handle((new CallToolRequest('mcp_x', $text))->withId(3), $session);
    $this->assertSame($text, $this->seen[2]->arguments);
  }

}
