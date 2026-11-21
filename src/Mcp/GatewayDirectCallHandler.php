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
    $arguments = $request->arguments ?? [];
    if (($arguments['tool_name'] ?? NULL) === $request->name
      && array_diff(array_keys($arguments), ['tool_name', 'arguments']) === []) {
      // From mcp/sdk 0.10 a nested object arrives as \stdClass. Only the
      // outer one is opened, so an empty object inside stays an object.
      $inner = $arguments['arguments'] ?? [];
      if ($inner instanceof \stdClass) {
        $inner = (array) $inner;
      }
      if (is_array($inner)) {
        $request = (new CallToolRequest($request->name, $inner))->withId($request->getId());
      }
    }
    return $this->door->handle($request, $session);
  }

}
