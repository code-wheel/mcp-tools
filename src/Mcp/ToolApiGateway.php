<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Mcp\Error\ToolErrorHandlerInterface;
use Drupal\tool\Tool\ToolDefinition;
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

  public function __construct(
    private readonly PluginManagerInterface $toolManager,
    private readonly ToolApiSchemaConverter $schemaConverter,
    private readonly LoggerInterface $logger,
    private readonly bool $includeAllTools = FALSE,
    private readonly string $allowedProviderPrefix = 'mcp_tools',
    private readonly ?ToolErrorHandlerInterface $errorHandler = NULL,
    private readonly ?EventDispatcherInterface $eventDispatcher = NULL,
  ) {
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
    $definitions = $this->toolManager->getDefinitions();
    $tools = [];
    $query = $query !== NULL ? trim($query) : NULL;

    foreach ($definitions as $pluginId => $definition) {
      if (!$definition instanceof ToolDefinition) {
        continue;
      }
      if (!$this->isToolAllowed($definition)) {
        continue;
      }

      $name = McpToolsServerFactory::pluginIdToMcpName((string) $pluginId);
      $label = $this->normalizeValue($definition->getLabel());
      $description = $this->normalizeValue($definition->getDescription());

      if ($query !== NULL && $query !== '') {
        $haystack = strtolower($name . ' ' . $label . ' ' . $description);
        if (!str_contains($haystack, strtolower($query))) {
          continue;
        }
      }

      $annotations = $this->schemaConverter->toolDefinitionToAnnotations($definition, (string) $pluginId);
      $hints = array_filter([
        'read_only' => $annotations['readOnlyHint'] ?? NULL,
        'destructive' => $annotations['destructiveHint'] ?? NULL,
        'idempotent' => $annotations['idempotentHint'] ?? NULL,
      ], static fn($value): bool => $value !== NULL);

      $tools[] = [
        'name' => $name,
        'label' => $label,
        'description' => $description,
        'provider' => (string) ($definition->getProvider() ?? ''),
        'hints' => $hints,
      ];
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
    $resolved = $this->resolveToolDefinition($tool_name);
    if (!$resolved) {
      return $this->errorResult('Unknown tool: ' . $tool_name);
    }

    [$definition, $pluginId] = $resolved;
    $annotations = $this->schemaConverter->toolDefinitionToAnnotations($definition, (string) $pluginId);
    $inputSchema = $this->schemaConverter->toolDefinitionToInputSchema($definition, (string) $pluginId);

    $structured = [
      'success' => TRUE,
      'name' => McpToolsServerFactory::pluginIdToMcpName((string) $pluginId),
      'plugin_id' => (string) $pluginId,
      'label' => $this->normalizeValue($definition->getLabel()),
      'description' => $this->normalizeValue($definition->getDescription()),
      'provider' => (string) ($definition->getProvider() ?? ''),
      'input_schema' => $inputSchema,
      'annotations' => $annotations,
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
   * Resolves a ToolDefinition from an MCP tool name or plugin ID.
   *
   * @return array{0: \Drupal\tool\Tool\ToolDefinition, 1: string}|null
   */
  private function resolveToolDefinition(string $toolName): ?array {
    $definitions = $this->toolManager->getDefinitions();

    $pluginId = $toolName;
    if (!isset($definitions[$pluginId])) {
      $pluginId = $this->mcpNameToPluginId($toolName);
    }

    $definition = $definitions[$pluginId] ?? NULL;
    if (!$definition instanceof ToolDefinition) {
      return NULL;
    }
    if (!$this->isToolAllowed($definition)) {
      return NULL;
    }

    return [$definition, (string) $pluginId];
  }

  /**
   * Determine whether a tool definition should be exposed.
   */
  private function isToolAllowed(ToolDefinition $definition): bool {
    if ($this->includeAllTools) {
      return TRUE;
    }

    $provider = $definition->getProvider() ?? '';
    if (!is_string($provider) || !str_starts_with($provider, $this->allowedProviderPrefix)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Converts MCP tool names back to Tool API plugin IDs.
   */
  private function mcpNameToPluginId(string $toolName): string {
    return str_replace('___', ':', $toolName);
  }

  /**
   * Normalizes values to scalars/arrays suitable for structuredContent.
   */
  private function normalizeValue(mixed $value): mixed {
    if ($value instanceof TranslatableMarkup) {
      return (string) $value;
    }

    if (is_array($value)) {
      $normalized = [];
      foreach ($value as $key => $item) {
        $normalized[$key] = $this->normalizeValue($item);
      }
      return $normalized;
    }

    if (is_object($value)) {
      if ($value instanceof \JsonSerializable) {
        return $value->jsonSerialize();
      }
      if (method_exists($value, '__toString')) {
        return (string) $value;
      }
      return get_class($value);
    }

    return $value;
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
