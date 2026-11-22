<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\mcp_tools\Mcp\Error\ToolErrorHandlerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\McpToolCallContext;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\RequestContext;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Gateway tools that expose discover/info/execute for Tool API plugins.
 */
class ToolApiGateway {

  public const DISCOVER_TOOL = 'mcp_tools_discover_tools';
  public const GET_INFO_TOOL = 'mcp_tools_get_tool_info';
  public const EXECUTE_TOOL = 'mcp_tools_execute_tool';

  /**
   * The tool executor.
   *
   * @var ToolApiCallToolHandler
   */
  private ToolApiCallToolHandler $toolExecutor;
  /**
   * The tool provider.
   *
   * @var DrupalToolProvider
   */
  private DrupalToolProvider $toolProvider;

  public function __construct(
    private readonly PluginManagerInterface $toolManager,
    private readonly ToolApiSchemaConverter $schemaConverter,
    private readonly LoggerInterface $logger,
    private readonly bool $includeAllTools = FALSE,
    private readonly string $allowedProviderPrefix = 'mcp_tools',
    private readonly ?ToolErrorHandlerInterface $errorHandler = NULL,
    private readonly ?EventDispatcherInterface $eventDispatcher = NULL,
    private readonly ?AccessManager $accessManager = NULL,
    private readonly ?McpToolCallContext $toolCallContext = NULL,
  ) {
    $this->toolProvider = new DrupalToolProvider(
      $this->toolManager,
      $this->schemaConverter,
      $this->logger,
      $this->includeAllTools,
      $this->allowedProviderPrefix,
      $this->accessManager,
    );

    $validator = new ToolInputValidator($this->schemaConverter, $this->logger);
    $this->toolExecutor = new ToolApiCallToolHandler(
      $this->toolManager,
      $this->logger,
      $this->includeAllTools,
      $this->allowedProviderPrefix,
      $validator,
      $this->errorHandler,
      $this->eventDispatcher,
      NULL,
      $this->accessManager,
      $this->toolCallContext,
    );
  }

  /**
   * Returns the underlying tool provider.
   *
   * Useful for external integrations that need direct access to tool discovery.
   */
  public function getToolProvider(): DrupalToolProvider {
    return $this->toolProvider;
  }

  /**
   * What a model is told after a tool name that does not exist.
   */
  public const FIND_HINT = ' Find tools with ' . self::DISCOVER_TOOL . '.';

  /**
   * Words in a search that say nothing about the tool that is wanted.
   */
  private const SEARCH_FILLER_WORDS = ['the', 'and', 'for', 'with', 'all', 'any', 'tool', 'tools'];

  /**
   * Whether this session can only read through the gateway.
   *
   * The execute tool can run every tool, so it was always described as one
   * that may change the site. A client that asks its user before such a
   * call then asks before every call in gateway mode, also for a read, and
   * refuses them all when nobody is there to ask. A session without the
   * write and the admin scope cannot change anything through it, and no
   * session can while the site is in read-only mode: then the hint says
   * "read only". The server builds these tools when a session starts, with
   * the scopes of that session.
   */
  private function sessionCanOnlyRead(): bool {
    if ($this->accessManager === NULL) {
      return FALSE;
    }
    if ($this->accessManager->isReadOnlyMode()) {
      return TRUE;
    }
    return !$this->accessManager->hasScope(AccessManager::SCOPE_WRITE)
      && !$this->accessManager->hasScope(AccessManager::SCOPE_ADMIN);
  }

  /**
   * Returns gateway tool definitions for server registration.
   *
   * @return array<int, array{
   *   handler: callable,
   *   name: string,
   *   description: string,
   *   annotations: \Mcp\Schema\ToolAnnotations,
   *   inputSchema: array<string, mixed>,
   *   }>
   */
  public function getGatewayTools(): array {
    return [
      [
        // Find and describe check what this session may call, and that
        // can load the user. Drupal pauses the current Fiber for an entity
        // load, and from mcp/sdk 0.10 the SDK fails a request whose
        // handler pauses for anything but the SDK. So the work runs in a
        // Fiber of its own, as a tool call does.
        'handler' => function (?string $query = NULL, ?int $limit = NULL): CallToolResult {
          return FiberIsolation::run(fn(): CallToolResult => $this->discoverTools($query, $limit));
        },
        'name' => self::DISCOVER_TOOL,
        'description' => 'Find tools by keyword. Each match comes with its description and the names of its inputs.',
        'annotations' => ToolAnnotations::fromArray([
          'title' => 'Discover Tools',
          'readOnlyHint' => TRUE,
          'idempotentHint' => TRUE,
          'openWorldHint' => FALSE,
        ]),
        'inputSchema' => $this->buildSchema([
          'query' => [
            'type' => 'string',
            'description' => 'Search term matched against tool name, label, description, and provider. Omit for a compact grouped overview of the catalog.',
          ],
          'limit' => [
            'type' => 'integer',
            'description' => 'Maximum matching tools to return (default 25, max 100).',
          ],
        ]),
      ],
      [
        'handler' => function (string $tool_name): CallToolResult {
          return FiberIsolation::run(fn(): CallToolResult => $this->getToolInfo($tool_name));
        },
        'name' => self::GET_INFO_TOOL,
        'description' => 'Get input schema and hints for a specific tool.',
        'annotations' => ToolAnnotations::fromArray([
          'title' => 'Get Tool Info',
          'readOnlyHint' => TRUE,
          'idempotentHint' => TRUE,
          'openWorldHint' => FALSE,
        ]),
        'inputSchema' => $this->buildSchema([
          'tool_name' => [
            'type' => 'string',
            'description' => 'Tool name from ' . self::DISCOVER_TOOL . '.',
          ],
        ], ['tool_name']),
      ],
      [
        'handler' => function (string $tool_name, RequestContext $context, ?array $arguments = NULL): CallToolResult {
          return $this->executeTool($tool_name, $arguments, $context);
        },
        'name' => self::EXECUTE_TOOL,
        'description' => 'Execute any available tool by name with arguments.',
        'annotations' => ToolAnnotations::fromArray($this->sessionCanOnlyRead() ? [
          'title' => 'Execute Tool',
          'readOnlyHint' => TRUE,
          'destructiveHint' => FALSE,
          'openWorldHint' => TRUE,
        ] : [
          'title' => 'Execute Tool',
          'readOnlyHint' => FALSE,
          'openWorldHint' => TRUE,
        ]),
        'inputSchema' => $this->buildSchema([
          'tool_name' => [
            'type' => 'string',
            'description' => 'Tool name from ' . self::DISCOVER_TOOL . '.',
          ],
          'arguments' => [
            // An object keyed by parameter name. The SDK decodes JSON to PHP
            // arrays before it checks this schema, so an empty object {}
            // arrives as an empty array: allow exactly that, and nothing
            // else that is a list.
            'type' => ['object', 'array'],
            'maxItems' => 0,
            'description' => 'Arguments to pass to the tool, as an object keyed by parameter name. Use {} or leave it out for a tool with no parameters.',
          ],
        ], ['tool_name']),
      ],
    ];
  }

  /**
   * Lists available Tool API tools with lightweight metadata.
   */
  public function discoverTools(?string $query = NULL, ?int $limit = NULL): CallToolResult {
    $allTools = $this->toolProvider->getTools();
    $query = $query !== NULL ? trim($query) : '';
    $limit = $limit !== NULL ? max(1, min(100, $limit)) : 25;

    // Without a query, return a compact grouped overview instead of the
    // full catalog: at 200+ tools the flat listing costs thousands of
    // context tokens per call, while a query-scoped call costs a few
    // hundred. The overview teaches the client what to query for.
    if ($query === '') {
      $groups = [];
      /** @var \CodeWheel\McpToolGateway\ToolInfo $toolInfo */
      foreach ($allTools as $toolInfo) {
        $key = (string) ($toolInfo->provider ?? 'other');
        $groups[$key]['provider'] = $key;
        $groups[$key]['count'] = ($groups[$key]['count'] ?? 0) + 1;
        if (count($groups[$key]['examples'] ?? []) < 3) {
          $groups[$key]['examples'][] = $toolInfo->name;
        }
      }
      ksort($groups);

      $structured = [
        'success' => TRUE,
        'mode' => 'overview',
        'total' => count($allTools),
        'groups' => array_values($groups),
        'hint' => 'Pass query (a keyword, tool name fragment, or provider from this list) to get matching tools with descriptions and input names, then ' . self::GET_INFO_TOOL . ' for the full input schema.',
      ];
      // Three examples a group cannot show the one tool most first
      // questions need. Name it, when this session has it.
      foreach ($allTools as $toolInfo) {
        if ($toolInfo->name === 'mcp_tools_get_site_status') {
          $structured['hint'] .= ' For the Drupal core version and a first look at the site: mcp_tools_get_site_status.';
          break;
        }
      }
      return new CallToolResult([new TextContent(json_encode($structured))], FALSE, $structured);
    }

    // The query as one phrase first. A model that asks in its own words
    // ("node type" for a content type) finds nothing that way, so the
    // words are then tried one by one: the tools that have all of them,
    // and failing that the tools that have some, most words first.
    // A plural finds what its singular finds: the tools are named
    // ..._view and ..._content_type, and a model asks for "views" and
    // "content types".
    $needle = self::singular(strtolower($query));
    $words = array_values(array_unique(array_map(self::singular(...), array_filter(
      preg_split('/[^a-z0-9_]+/', $needle) ?: [],
      static fn(string $word): bool => strlen($word) > 2 && !in_array($word, self::SEARCH_FILLER_WORDS, TRUE),
    ))));
    $byPhrase = [];
    $byWords = [];
    /** @var \CodeWheel\McpToolGateway\ToolInfo $toolInfo */
    foreach ($allTools as $toolInfo) {
      $haystack = strtolower($toolInfo->name . ' ' . $toolInfo->label . ' ' . $toolInfo->description . ' ' . (string) ($toolInfo->provider ?? ''));
      if (str_contains($haystack, $needle)) {
        $byPhrase[] = $toolInfo;
        continue;
      }
      $hits = count(array_filter($words, static fn(string $word): bool => str_contains($haystack, $word)));
      if ($hits > 0) {
        $byWords[$hits][] = $toolInfo;
      }
    }

    // Best match first: a model often asks for ten and reads no further.
    $best = static fn(array $tools): array => self::nameMatchesFirst($tools, $words);
    $match = NULL;
    $found = $best($byPhrase);
    if ($found === [] && $words !== []) {
      if (isset($byWords[count($words)])) {
        $found = $best($byWords[count($words)]);
        $match = 'all words';
      }
      elseif ($byWords !== []) {
        krsort($byWords);
        $found = array_merge(...array_map($best, array_values($byWords)));
        $match = 'some words';
      }
    }

    $tools = array_map(
      static fn($toolInfo) => $toolInfo->toDiscoverySummary() + ['inputs' => self::inputNames($toolInfo->inputSchema)],
      array_slice($found, 0, $limit),
    );
    $structured = [
      'success' => TRUE,
      'count' => count($tools),
      'total_matches' => count($found),
      'tools' => $tools,
    ];
    if ($match !== NULL) {
      $structured['match'] = $match;
    }
    if ($found === []) {
      // An empty answer is where a model gives up. Say what there is.
      $providers = array_values(array_unique(array_map(static fn($toolInfo): string => (string) ($toolInfo->provider ?? 'other'), $allTools)));
      sort($providers);
      $structured['hint'] = 'No tool matches "' . $query . '". Try one keyword (for example: content type, field, view, menu, role, user), or one of these groups: ' . implode(', ', $providers) . '. Call ' . self::DISCOVER_TOOL . ' without a query for the overview.';
    }
    elseif (count($found) > count($tools)) {
      $structured['hint'] = 'Result truncated to ' . count($tools) . ' of ' . count($found) . ' matches. Narrow the query or raise limit.';
    }

    return new CallToolResult([new TextContent(json_encode($structured))], FALSE, $structured);
  }

  /**
   * Puts the tools whose name has the search words first.
   *
   * A word in a tool's name says more than the same word somewhere in its
   * description: "view" finds the config tool too ("View a specific
   * configuration object"). A word counts when a word of the name starts
   * with it, so "view" is in mcp_create_view and not in
   * mcp_config_preview. The more of the words a name has the better, and
   * a short name that is mostly those words comes before a long one. A
   * word in the label counts half. Tools with the same score keep the
   * order of the catalog.
   *
   * @param \CodeWheel\McpToolGateway\ToolInfo[] $tools
   *   The tools that matched.
   * @param string[] $words
   *   The words of the search, in lower case.
   *
   * @return \CodeWheel\McpToolGateway\ToolInfo[]
   *   The same tools, best match first.
   */
  private static function nameMatchesFirst(array $tools, array $words): array {
    $split = static fn(string $text): array => array_values(array_diff(
      preg_split('/[^a-z0-9]+/', strtolower($text)) ?: [],
      ['', 'mcp', 'tools'],
    ));
    $has = static fn(array $parts, string $word): bool => array_filter($parts, static fn(string $part): bool => str_starts_with($part, $word)) !== [];
    $scores = [];
    foreach ($tools as $key => $toolInfo) {
      $nameWords = $split((string) $toolInfo->name);
      $labelWords = $split((string) $toolInfo->label);
      $inName = count(array_filter($words, static fn(string $word): bool => $has($nameWords, $word)));
      $inLabel = count(array_filter($words, static fn(string $word): bool => !$has($nameWords, $word) && $has($labelWords, $word)));
      $scores[$key] = 2 * $inName + $inLabel + ($inName > 0 ? $inName / max(1, count($nameWords)) : 0);
    }
    $order = array_keys($tools);
    usort($order, static fn(int $a, int $b): int => [$scores[$b], $a] <=> [$scores[$a], $b]);
    return array_map(static fn(int $key) => $tools[$key], $order);
  }

  /**
   * A search text without the plural ending of its last word.
   *
   * What is left is the start of both forms ("view" of "views", "vocabular"
   * of "vocabulary" and "vocabularies"), and the search looks for it
   * anywhere in a tool's texts. So the plural finds everything the
   * singular finds. A word that only ends in s loses it too ("status"
   * becomes "statu") and still finds itself. A short word is left alone:
   * "news" would become "new" and find every "Create a new ...".
   */
  private static function singular(string $text): string {
    if (!preg_match('/[a-z0-9]+$/', $text, $last) || strlen($last[0]) < 5) {
      return $text;
    }
    $cut = match (TRUE) {
      strlen($last[0]) > 6 && str_ends_with($last[0], 'ies') => 3,
      (bool) preg_match('/(s|x|z|ch|sh)es$/', $last[0]) => 2,
      str_ends_with($last[0], 's') && !str_ends_with($last[0], 'ss') => 1,
      default => 0,
    };
    return $cut > 0 ? substr($text, 0, -$cut) : $text;
  }

  /**
   * The names of a tool's inputs, the required ones apart.
   *
   * A model that has found a tool often calls it at once and guesses the
   * names of the arguments ("title" where the tool takes "query"). The
   * names in the search result cost a few tokens and save that call.
   *
   * @param array<string, mixed> $inputSchema
   *   The tool's input schema.
   *
   * @return array{required: string[], optional: string[]}
   *   The input names.
   */
  private static function inputNames(array $inputSchema): array {
    $properties = $inputSchema['properties'] ?? [];
    $names = is_array($properties) ? array_map('strval', array_keys($properties)) : [];
    $required = array_values(array_intersect($names, (array) ($inputSchema['required'] ?? [])));
    return ['required' => $required, 'optional' => array_values(array_diff($names, $required))];
  }

  /**
   * Returns schema and hints for a single tool.
   */
  public function getToolInfo(string $tool_name): CallToolResult {
    $toolInfo = $this->toolProvider->getTool($tool_name);
    // A name with only "tools_" left out: the tool with it is described,
    // under its real name.
    $meant = $toolInfo === NULL ? ToolNameSuggester::meant($tool_name) : NULL;
    if ($meant !== NULL) {
      $toolInfo = $this->toolProvider->getTool($meant);
    }
    if ($toolInfo === NULL) {
      // A name a model made up: say which real names are close.
      $closest = ToolNameSuggester::closest($tool_name, array_map(static fn($info): string => (string) $info->name, $this->toolProvider->getTools()));
      return $this->errorResult(
        'Unknown tool: ' . $tool_name . ($closest !== [] ? '. Did you mean ' . implode(', ', $closest) . '?' : '.') . self::FIND_HINT,
        $closest !== [] ? ['did_you_mean' => $closest] : [],
      );
    }

    $structured = [
      'success' => TRUE,
      ...$toolInfo->toDetailedInfo(),
      'plugin_id' => $toolInfo->metadata['plugin_id'] ?? '',
    ];

    $content = [new TextContent(json_encode($structured))];

    return new CallToolResult($content, FALSE, $structured);
  }

  /**
   * Executes a Tool API plugin via the gateway.
   */
  public function executeTool(string $tool_name, ?array $arguments, RequestContext $context): CallToolResult {
    $arguments = $arguments ?? [];
    if (!is_array($arguments)) {
      return $this->errorResult('Arguments must be an object.', ['tool' => $tool_name]);
    }

    // The gateway's own three tools are not behind this one.
    if (in_array($tool_name, [self::DISCOVER_TOOL, self::GET_INFO_TOOL, self::EXECUTE_TOOL], TRUE)) {
      return $this->errorResult($tool_name . ' is not run through ' . self::EXECUTE_TOOL . '. Call it by its own name.', ['tool' => $tool_name]);
    }

    $request = (new CallToolRequest($tool_name, $arguments))->withId('mcp_tools_gateway');
    $response = $this->toolExecutor->handle($request, $context->getSession());

    if ($response instanceof Response && $response->result instanceof CallToolResult) {
      return $response->result;
    }

    if ($response instanceof Error) {
      // The handler has added the closest names to "Unknown tool". Here
      // the model also has a tool to look for more.
      $message = $response->message;
      if ($response->code === Error::METHOD_NOT_FOUND) {
        $message = rtrim($message, '.') . (str_ends_with($message, '?') ? '' : '.') . self::FIND_HINT;
      }
      return $this->errorResult($message, [
        'tool' => $tool_name,
        'code' => $response->code,
      ]);
    }

    return $this->errorResult('Unexpected tool response.', ['tool' => $tool_name]);
  }

  /**
   * Creates a tool error result.
   *
   * @param string $message
   *   The error message.
   * @param array<string, mixed> $structured
   *   The structured error data.
   */
  private function errorResult(string $message, array $structured = []): CallToolResult {
    $payload = ['success' => FALSE, 'error' => $message] + $structured;
    $content = [new TextContent($message)];
    return new CallToolResult($content, TRUE, $payload);
  }

  /**
   * Builds a simple JSON schema for gateway tools.
   *
   * @param array<string, mixed> $properties
   *   The schema properties.
   * @param string[] $required
   *   The required property names.
   *
   * @return array<string, mixed>
   *   The JSON schema.
   */
  private function buildSchema(array $properties, array $required = []): array {
    $schema = [
      'type' => 'object',
      'properties' => !empty($properties) ? $properties : new \stdClass(),
      // Same fail-loudly policy as generated tool schemas: a mistyped
      // parameter name must be rejected, not silently dropped.
      'additionalProperties' => FALSE,
    ];

    if (!empty($required)) {
      $schema['required'] = $required;
    }

    return $schema;
  }

}
