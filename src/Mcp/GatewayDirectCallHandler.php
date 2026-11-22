<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * In gateway mode, takes a call of a tool by its own name.
 *
 * Gateway mode lists three tools: find, describe, run. A model that has
 * found a tool often calls it by the name it found, and not through the
 * run tool. The server then said "Tool not found", and a small model
 * tried the same call again until it ran out of turns.
 *
 * Such a call now goes through the same door as every other call: the
 * same access check, scopes, limits and audit line. The three gateway
 * tools themselves are left to the server.
 *
 * @implements \Mcp\Server\Handler\Request\RequestHandlerInterface<\Mcp\Schema\Result\CallToolResult>
 */
final class GatewayDirectCallHandler implements RequestHandlerInterface {

  /**
   * Constructs the handler.
   *
   * @param \Mcp\Server\Handler\Request\RequestHandlerInterface $door
   *   The handler that runs a Tool API tool by its name.
   * @param string[] $gatewayToolNames
   *   The names of the gateway's own tools.
   */
  public function __construct(
    private readonly RequestHandlerInterface $door,
    private readonly array $gatewayToolNames,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function supports(Request $request): bool {
    return $request instanceof CallToolRequest
      && !in_array($request->name, $this->gatewayToolNames, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, SessionInterface $session): Response|Error {
    assert($request instanceof CallToolRequest);
    // A model also mixes the two ways: the tool's own name, with the
    // arguments in the shape the run tool takes ({"tool_name": ...,
    // "arguments": {...}}). Then the inner arguments are meant.
    //
    // The run tool itself is also called with "tools_" left out of its
    // name (mcp_execute_tool). The tool it names is run through the door,
    // as a call by that tool's own name is.
    $arguments = $request->arguments ?? [];
    $named = $arguments['tool_name'] ?? NULL;
    $asRunTool = ToolNameSuggester::withTools($request->name) === ToolApiGateway::EXECUTE_TOOL
      && is_string($named) && !in_array($named, $this->gatewayToolNames, TRUE);
    if (($named === $request->name || $asRunTool)
      && array_diff(array_keys($arguments), ['tool_name', 'arguments']) === []) {
      // From mcp/sdk 0.10 a nested object arrives as \stdClass. Only the
      // outer one is opened, so an empty object inside stays an object.
      $inner = $arguments['arguments'] ?? [];
      if ($inner instanceof \stdClass) {
        $inner = (array) $inner;
      }
      if (is_array($inner)) {
        $request = (new CallToolRequest($named, $inner))->withId($request->getId());
      }
    }
    $response = $this->door->handle($request, $session);
    // A name that does not exist: the door has added the closest names.
    // In gateway mode there is also a tool to look for more.
    if ($response instanceof Error && $response->code === Error::METHOD_NOT_FOUND) {
      // The door knows the catalog, not the gateway's own three tools. A
      // name close to one of those (mcp_execute_tool) gets that one.
      $own = ToolNameSuggester::closest($request->name, $this->gatewayToolNames, 1);
      $message = $own !== []
        ? 'Unknown tool: ' . $request->name . '. Did you mean ' . $own[0] . '?'
        : rtrim($response->message, '.') . (str_ends_with($response->message, '?') ? '' : '.');
      $message .= ToolApiGateway::FIND_HINT;
      return Error::forMethodNotFound($message, $request->getId());
    }
    return $response;
  }

}
