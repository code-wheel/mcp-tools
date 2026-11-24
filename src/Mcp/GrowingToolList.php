<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Notification\ToolListChangedNotification;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * A tool list that grows with the session: what it found, it is shown.
 *
 * In gateway mode about half of all calls went to finding a tool, not to
 * using it (34% discover, 17% get info, in 406 runs of our own). Here a
 * session starts with the three gateway tools and a few core ones; a tool
 * that get info was asked about joins the end of its list, and the client
 * is told the list changed. A client that does not listen
 * keeps the gateway's way, so it is never worse off.
 *
 * @implements \Mcp\Server\Handler\Request\RequestHandlerInterface<\Mcp\Schema\Result\ListToolsResult>
 */
final class GrowingToolList implements RequestHandlerInterface {

  /**
   * Where a session keeps the names of the tools it was shown.
   */
  public const SESSION_KEY = 'mcp_tools.shown_tools';

  /**
   * Builds the list from the registry and the names every session is shown.
   *
   * @param object $registry
   *   The server's registry, as Mcp\Capability\Registry (mcp/sdk 0.9 and
   *   before) or Mcp\Server\Capability\Registry (0.10).
   * @param string[] $always
   *   The MCP names every session is shown: the gateway's and the core ones.
   */
  public function __construct(
    private readonly object $registry,
    private readonly array $always,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function supports(Request $request): bool {
    return $request instanceof ListToolsRequest;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, SessionInterface $session): Response {
    assert($request instanceof ListToolsRequest);
    // The always-shown first, then each found tool in the order it was
    // found: the list only grows at its end, so a client that caches the
    // start of a prompt keeps all of it but the new tools.
    $byName = [];
    foreach ($this->registry->getTools(NULL)->references as $tool) {
      $byName[$tool->name] = $tool;
    }
    $tools = [];
    foreach (array_unique(array_merge($this->always, (array) $session->get(self::SESSION_KEY, []))) as $name) {
      if (isset($byName[$name])) {
        $tools[] = $byName[$name];
      }
    }
    return new Response($request->getId(), new ListToolsResult($tools, NULL));
  }

  /**
   * Adds tools to the session's list; says whether any were new.
   *
   * @param \Mcp\Server\Session\SessionInterface $session
   *   The session.
   * @param string[] $names
   *   MCP names.
   */
  public static function show(SessionInterface $session, array $names): bool {
    $before = (array) $session->get(self::SESSION_KEY, []);
    $after = array_values(array_unique(array_merge($before, $names)));
    if (count($after) === count($before)) {
      return FALSE;
    }
    $session->set(self::SESSION_KEY, $after);
    $session->save();
    return TRUE;
  }

  /**
   * Tells the client the list changed, when the SDK and the moment allow.
   *
   * The SDK sends a notification from a call only while the call runs in
   * its fiber. Before mcp/sdk 0.10 there is none, and the client keeps the
   * gateway's way.
   */
  public static function tell(SessionInterface $session): void {
    if (!class_exists(ClientGateway::class) || !class_exists(ToolListChangedNotification::class) || \Fiber::getCurrent() === NULL) {
      return;
    }
    try {
      (new ClientGateway($session))->notify(new ToolListChangedNotification());
    }
    catch (\Throwable) {
      // A client that cannot be told keeps the gateway's way.
    }
  }

}
