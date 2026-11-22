<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_policy;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\mcp_tools\Policy\ToolPolicy;
use Drupal\mcp_tools\Policy\ToolRefusal;
use Drupal\tool\Tool\ToolDefinition;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Mcp\Event\ErrorEvent;
use Mcp\Event\ResponseEvent;
use Mcp\Schema\JsonRpc\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The rules of MCP Tools in front of MCP Server's HTTP endpoint.
 *
 * MCP Server runs Tool API tools through its tool bridge. Its own rule is
 * the permission of the acting user. This adds, for a tool of any module
 * that is called there: this site's scopes, read-only mode and config-only
 * mode, the write limits, and an audit line.
 *
 * It sits where MCP Server asks for such rules: a PSR-15 middleware tagged
 * mcp_server.transport_middleware, which runs before the MCP SDK sees the
 * request. A call that is refused never reaches MCP Server. It is answered
 * here with a tool result that says why, which a model can read. How a
 * call that was let in has ended comes from the SDK's own events.
 *
 * What it cannot do:
 * - MCP Server over STDIO (drush) has no HTTP stack, so no rule holds
 *   there.
 * - It cannot see whether a call that came back as an error had run. Such
 *   a call keeps its count against the limit and gets an audit line that
 *   says "failed". MCP Tools' own server knows, and gives the count back.
 */
final class McpServerPolicy implements MiddlewareInterface, EventSubscriberInterface {

  /**
   * Calls that were let in, by JSON-RPC ID, until their outcome is known.
   *
   * @var array<string, list<array{0: \Drupal\tool\Tool\ToolDefinition, 1: array<string, mixed>}>>
   */
  private array $pending = [];

  public function __construct(
    private readonly ToolPolicy $policy,
    private readonly PluginManagerInterface $mcpServerTools,
    private readonly PluginManagerInterface $tools,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ResponseEvent::class => 'onResponse',
      ErrorEvent::class => 'onError',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    if (strtoupper($request->getMethod()) !== 'POST') {
      return $handler->handle($request);
    }
    // MCP Server reads the body after this, so it gets a fresh one.
    $body = (string) $request->getBody();
    $request = $request->withBody(Utils::streamFor($body));
    $decoded = json_decode($body, TRUE);
    if (!is_array($decoded)) {
      return $handler->handle($request);
    }
    $batch = array_is_list($decoded);
    $messages = $batch ? $decoded : [$decoded];

    // One entry per message of the POST, in its order: NULL for what is not
    // judged here, the refusal, or the tool and its arguments.
    $verdicts = [];
    $anyRefused = FALSE;
    foreach ($messages as $at => $message) {
      $verdicts[$at] = NULL;
      $definition = self::isRequest($message) && $message['method'] === 'tools/call'
        ? $this->toolBehind($message['params']['name'] ?? NULL)
        : NULL;
      if ($definition === NULL) {
        continue;
      }
      $arguments = $message['params']['arguments'] ?? [];
      $arguments = is_array($arguments) ? $arguments : [];
      $refusal = $this->policy->admit($definition, $arguments);
      if ($refusal !== NULL) {
        $verdicts[$at] = $refusal;
        $anyRefused = TRUE;
        continue;
      }
      $verdicts[$at] = [$definition, $arguments];
    }

    if (!$anyRefused) {
      foreach ($verdicts as $at => $verdict) {
        if (is_array($verdict)) {
          $this->pending[self::key($messages[$at]['id'])][] = $verdict;
        }
      }
      return $handler->handle($request);
    }

    // MCP Server would run the rest of the POST, and part of a POST cannot
    // be answered from here. So none of it runs: every request in it gets
    // an answer, and what was counted for the others is given back.
    $answers = [];
    foreach ($messages as $at => $message) {
      $verdict = $verdicts[$at];
      if (is_array($verdict)) {
        $this->policy->giveBack($verdict[0]);
      }
      if (!self::isRequest($message)) {
        continue;
      }
      $answers[] = $verdict instanceof ToolRefusal
        ? self::refusalResult($message['id'], $verdict)
        : [
          'jsonrpc' => '2.0',
          'id' => $message['id'],
          'error' => ['code' => -32000, 'message' => 'Not run: it was sent together with a tool call that was refused.'],
        ];
    }
    return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($batch ? $answers : $answers[0]));
  }

  /**
   * Whether a message is one the MCP SDK takes for a request.
   *
   * The same reading as the SDK's: a message with an error or a result is
   * an answer, and a request has a method and an ID that is a string or an
   * integer. Anything else is not run there, so it is not judged here.
   */
  private static function isRequest(mixed $message): bool {
    return is_array($message)
      && !isset($message['error'])
      && !isset($message['result'])
      && isset($message['method'], $message['id'])
      && is_string($message['method'])
      && (is_string($message['id']) || is_int($message['id']));
  }

  /**
   * A key for a JSON-RPC ID that keeps 1 and "1" apart, as JSON-RPC does.
   */
  private static function key(int|string $id): string {
    return (is_int($id) ? 'i' : 's') . $id;
  }

  /**
   * A call that was let in has an answer: its audit line.
   */
  public function onResponse(ResponseEvent $event): void {
    $call = $this->take($event->getRequest());
    if ($call === NULL) {
      return;
    }
    $result = $event->getResponse()->result;
    $failed = is_object($result) ? (bool) ($result->isError ?? FALSE) : (is_array($result) && !empty($result['isError']));
    $this->policy->record($call[0], $call[1], !$failed);
  }

  /**
   * A call that was let in ended in a protocol error: it did not run.
   */
  public function onError(ErrorEvent $event): void {
    $call = $this->take($event->getRequest());
    if ($call !== NULL) {
      $this->policy->giveBack($call[0]);
    }
  }

  /**
   * Takes a call off the list of those that were let in.
   *
   * @return array{0: \Drupal\tool\Tool\ToolDefinition, 1: array<string, mixed>}|null
   *   The tool and its arguments, or NULL for a request this did not let in.
   */
  private function take(Request $request): ?array {
    $key = self::key($request->getId());
    if (empty($this->pending[$key])) {
      return NULL;
    }
    // A list, in the order the calls came in: a POST may use one ID twice.
    $call = array_shift($this->pending[$key]);
    if ($this->pending[$key] === []) {
      unset($this->pending[$key]);
    }
    return $call;
  }

  /**
   * The Tool API tool behind a name MCP Server offers, if there is one.
   *
   * MCP Server also has tools of its own that are no Tool API tools. Those
   * are not judged here.
   */
  private function toolBehind(mixed $name): ?ToolDefinition {
    if (!is_string($name) || $name === '') {
      return NULL;
    }
    $offered = $this->mcpServerTools->getDefinition($name, FALSE);
    $toolId = is_object($offered) ? ($offered->toolId ?? NULL) : NULL;
    if (!is_string($toolId) || $toolId === '') {
      return NULL;
    }
    // The bridge stores ":" in a Tool API ID as "___".
    $definition = $this->tools->getDefinition(str_replace('___', ':', $toolId), FALSE);
    return $definition instanceof ToolDefinition ? $definition : NULL;
  }

  /**
   * A refusal as a tool result: text the client's model can read.
   *
   * No structured content: a client checks that against the tool's output
   * schema, which a refusal does not follow.
   *
   * @return array<string, mixed>
   *   The JSON-RPC response.
   */
  private static function refusalResult(int|string $id, ToolRefusal $refusal): array {
    return [
      'jsonrpc' => '2.0',
      'id' => $id,
      'result' => [
        'content' => [['type' => 'text', 'text' => $refusal->message]],
        'isError' => TRUE,
      ],
    ];
  }

}
