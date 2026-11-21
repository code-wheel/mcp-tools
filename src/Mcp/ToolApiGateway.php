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
        'handler' => function (?string $query = NULL, ?int $limit = NULL): CallToolResult {
          return $this->discoverTools($query, $limit);
        },
        'name' => self::DISCOVER_TOOL,
        'description' => 'List available MCP Tools with optional filtering.',
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
          return $this->getToolInfo($tool_name);
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
        'annotations' => ToolAnnotations::fromArray([
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
        'hint' => 'Pass query (a keyword, tool name fragment, or provider from this list) to get matching tools with descriptions, then ' . self::GET_INFO_TOOL . ' for the input schema.',
      ];
      return new CallToolResult([new TextContent(json_encode($structured))], FALSE, $structured);
    }

    $tools = [];
    $matched = 0;
    /** @var \CodeWheel\McpToolGateway\ToolInfo $toolInfo */
    foreach ($allTools as $toolInfo) {
      $haystack = strtolower($toolInfo->name . ' ' . $toolInfo->label . ' ' . $toolInfo->description . ' ' . (string) ($toolInfo->provider ?? ''));
      if (!str_contains($haystack, strtolower($query))) {
        continue;
      }
      $matched++;
      if (count($tools) < $limit) {
        $tools[] = $toolInfo->toDiscoverySummary();
      }
    }

    $structured = [
      'success' => TRUE,
      'count' => count($tools),
      'total_matches' => $matched,
      'tools' => $tools,
    ];
    if ($matched > count($tools)) {
      $structured['hint'] = 'Result truncated to ' . count($tools) . ' of ' . $matched . ' matches. Narrow the query or raise limit.';
    }

    return new CallToolResult([new TextContent(json_encode($structured))], FALSE, $structured);
  }

  /**
   * Returns schema and hints for a single tool.
   */
  public function getToolInfo(string $tool_name): CallToolResult {
    $toolInfo = $this->toolProvider->getTool($tool_name);
    if ($toolInfo === NULL) {
      return $this->errorResult('Unknown tool: ' . $tool_name);
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

    $request = (new CallToolRequest($tool_name, $arguments))->withId('mcp_tools_gateway');
    $response = $this->toolExecutor->handle($request, $context->getSession());

    if ($response instanceof Response && $response->result instanceof CallToolResult) {
      return $response->result;
    }

    if ($response instanceof Error) {
      return $this->errorResult($response->message, [
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
