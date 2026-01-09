<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use CodeWheel\McpToolGateway\ToolInfo;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\mcp_tools\Mcp\Error\ToolErrorHandlerInterface;
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

  public const DISCOVER_TOOL = 'mcp_tools/discover-tools';
  public const GET_INFO_TOOL = 'mcp_tools/get-tool-info';
  public const EXECUTE_TOOL = 'mcp_tools/execute-tool';

  private ToolApiCallToolHandler $toolExecutor;
  private DrupalToolProvider $toolProvider;

  public function __construct(
    private readonly PluginManagerInterface $toolManager,
    private readonly ToolApiSchemaConverter $schemaConverter,
    private readonly LoggerInterface $logger,
    private readonly bool $includeAllTools = FALSE,
    private readonly string $allowedProviderPrefix = 'mcp_tools',
    private readonly ?ToolErrorHandlerInterface $errorHandler = NULL,
    private readonly ?EventDispatcherInterface $eventDispatcher = NULL,
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
   * }>
   */
  public function getGatewayTools(): array {
    return [
      [
        'handler' => function (?string $query = NULL): CallToolResult {
          return $this->discoverTools($query);
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
            'description' => 'Optional search term for name/label/description.',
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
            'description' => 'Tool name from discover-tools.',
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
            'description' => 'Tool name from discover-tools.',
          ],
          'arguments' => [
            'type' => 'object',
            'description' => 'Arguments to pass to the tool.',
          ],
        ], ['tool_name']),
      ],
    ];
  }

  /**
   * Lists available Tool API tools with lightweight metadata.
   */
  public function discoverTools(?string $query = NULL): CallToolResult {
    $allTools = $this->toolProvider->getTools();
    $tools = [];
    $query = $query !== NULL ? trim($query) : NULL;

    /** @var ToolInfo $toolInfo */
    foreach ($allTools as $toolInfo) {
      if ($query !== NULL && $query !== '') {
        $haystack = strtolower($toolInfo->name . ' ' . $toolInfo->label . ' ' . $toolInfo->description);
        if (!str_contains($haystack, strtolower($query))) {
          continue;
        }
      }

      $tools[] = $toolInfo->toDiscoverySummary();
    }

    $structured = [
      'success' => TRUE,
      'count' => count($tools),
      'tools' => $tools,
    ];

    $text = 'Found ' . count($tools) . ' tools.';
    if (!empty($tools)) {
      $text .= "\n" . json_encode($structured, JSON_PRETTY_PRINT);
    }

    return new CallToolResult([new TextContent($text)], FALSE, $structured);
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

    $content = [new TextContent(json_encode($structured, JSON_PRETTY_PRINT))];

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
   * @param array<string, mixed> $structured
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
   * @param string[] $required
   *
   * @return array<string, mixed>
   */
  private function buildSchema(array $properties, array $required = []): array {
    $schema = [
      'type' => 'object',
      'properties' => !empty($properties) ? $properties : new \stdClass(),
    ];

    if (!empty($required)) {
      $schema['required'] = $required;
    }

    return $schema;
  }

}
