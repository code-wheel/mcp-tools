<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * Reading a tool shows it: get info grows the session's list.
 *
 * It answers the two gateway tools that find tools, as the gateway does,
 * and adds the tool get info named to the session's list
 * (GrowingToolList). From then on the model calls it by name.
 *
 * @implements \Mcp\Server\Handler\Request\RequestHandlerInterface<\Mcp\Schema\Result\CallToolResult>
 */
final class GrowingFindHandler implements RequestHandlerInterface {

  public function __construct(
    private readonly ToolApiGateway $gateway,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function supports(Request $request): bool {
    return $request instanceof CallToolRequest
      && in_array($request->name, [ToolApiGateway::DISCOVER_TOOL, ToolApiGateway::GET_INFO_TOOL], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, SessionInterface $session): Response {
    assert($request instanceof CallToolRequest);
    $arguments = (array) ($request->arguments ?? []);
    if ($request->name === ToolApiGateway::DISCOVER_TOOL) {
      $query = isset($arguments['query']) && is_scalar($arguments['query']) ? (string) $arguments['query'] : NULL;
      $limit = isset($arguments['limit']) && is_numeric($arguments['limit']) ? (int) $arguments['limit'] : NULL;
      $result = FiberIsolation::run(fn(): CallToolResult => $this->gateway->discoverTools($query, $limit));
      // A search shows what is there; only a tool the model asks about
      // joins its list. With five a search joining, a list held 47 tools
      // after twelve tasks and twice the gateway's tokens; this way 13.
      $found = [];
    }
    else {
      $name = isset($arguments['tool_name']) && is_scalar($arguments['tool_name']) ? (string) $arguments['tool_name'] : '';
      $result = FiberIsolation::run(fn(): CallToolResult => $this->gateway->getToolInfo($name));
      $found = $result->isError ? [] : array_filter([(string) ($result->structuredContent['name'] ?? '')]);
    }
    if ($found !== [] && GrowingToolList::show($session, array_map('strval', $found))) {
      GrowingToolList::tell($session);
    }
    return new Response($request->getId(), $result);
  }

}
