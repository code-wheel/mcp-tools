<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use CodeWheel\McpToolGateway\ExecutionContext;
use Drupal\mcp_tools\Service\AccessManager;
use CodeWheel\McpToolGateway\ToolExecutionException;
use CodeWheel\McpToolGateway\ToolInfo;
use CodeWheel\McpToolGateway\ToolNotFoundException;
use CodeWheel\McpToolGateway\ToolProviderInterface;
use CodeWheel\McpToolGateway\ToolResult;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Policy\ToolRefusal;
use Drupal\tool\FailureCategory;
use Drupal\tool\Tool\Invoker;
use Drupal\tool\Tool\ToolDefinition;
use Psr\Log\LoggerInterface;

/**
 * Adapter that exposes Drupal's Tool API through ToolProviderInterface.
 *
 * This allows the Drupal module to use the standardized mcp-tool-gateway
 * package for tool discovery and execution.
 */
class DrupalToolProvider implements ToolProviderInterface {

  /**
   * Cached tool definitions.
   *
   * @var array<string, ToolInfo>|null
   */
  private ?array $toolCache = NULL;

  public function __construct(
    private readonly PluginManagerInterface $toolManager,
    private readonly ToolApiSchemaConverter $schemaConverter,
    private readonly LoggerInterface $logger,
    private readonly bool $includeAllTools = FALSE,
    private readonly string $allowedProviderPrefix = 'mcp_tools',
    private readonly ?AccessManager $accessManager = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getTools(): array {
    if ($this->toolCache !== NULL) {
      return $this->toolCache;
    }

    $definitions = $this->toolManager->getDefinitions();
    $tools = [];

    foreach ($definitions as $pluginId => $definition) {
      if (!$definition instanceof ToolDefinition) {
        continue;
      }
      if (!$this->isToolAllowed($definition)) {
        continue;
      }

      $toolInfo = $this->createToolInfo($definition, (string) $pluginId);
      $tools[$toolInfo->name] = $toolInfo;
    }

    $this->toolCache = $tools;
    return $tools;
  }

  /**
   * {@inheritdoc}
   */
  public function getTool(string $toolName): ?ToolInfo {
    $tools = $this->getTools();

    if (isset($tools[$toolName])) {
      return $tools[$toolName];
    }

    // Try resolving as plugin ID.
    $pluginId = $this->mcpNameToPluginId($toolName);
    foreach ($tools as $tool) {
      if ($tool->metadata['plugin_id'] === $pluginId) {
        return $tool;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(string $toolName, array $arguments, ?ExecutionContext $context = NULL): ToolResult {
    $toolInfo = $this->getTool($toolName);
    if ($toolInfo === NULL) {
      throw new ToolNotFoundException("Tool not found: $toolName");
    }

    $pluginId = $toolInfo->metadata['plugin_id'] ?? $this->mcpNameToPluginId($toolName);

    // Our server runs gateway calls through ToolApiCallToolHandler. This
    // method is for code that uses the gateway library's own classes. It
    // goes the same way: Tool API's runner, with the site's rules around it.
    try {
      $definition = $this->toolManager->getDefinition($pluginId);
      assert($definition instanceof ToolDefinition);
      // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
      $outcome = \Drupal::service('mcp_tools.tool_policy')->run($definition, $arguments, new Invoker('mcp_tools'), $this->accessManager !== NULL);
    }
    catch (PluginException $e) {
      $this->logger->error('Failed to instantiate tool @name: @message', [
        '@name' => $toolName,
        '@message' => $e->getMessage(),
      ]);
      throw new ToolExecutionException("Failed to instantiate tool: {$e->getMessage()}", 0, $e);
    }
    catch (\Throwable $e) {
      $this->logger->error('Tool @name execution failed: @message', [
        '@name' => $toolName,
        '@message' => $e->getMessage(),
      ]);
      throw new ToolExecutionException("Tool execution failed: {$e->getMessage()}", 0, $e);
    }

    if ($outcome instanceof ToolRefusal) {
      return ToolResult::error($outcome->message, $outcome->reason === ToolRefusal::LIMIT ? ['retry_after' => $outcome->retryAfter] : []);
    }
    $result = $outcome->getResult();
    if (!$outcome->hasExecuted() && $result->getFailureCategory() === FailureCategory::Access) {
      return ToolResult::error('Access denied.');
    }
    $message = (string) $result->getMessage();
    if (!$result->isSuccess()) {
      return ToolResult::error($message !== '' ? $message : 'Tool execution failed');
    }
    return ToolResult::success($message !== '' ? $message : 'Tool executed successfully', $this->normalizeArray($result->getContextValues()));
  }

  /**
   * Creates a ToolInfo from a Drupal ToolDefinition.
   */
  private function createToolInfo(ToolDefinition $definition, string $pluginId): ToolInfo {
    $name = McpToolsServerFactory::pluginIdToMcpName($pluginId);
    $annotations = $this->schemaConverter->toolDefinitionToAnnotations($definition, $pluginId);
    $inputSchema = $this->schemaConverter->toolDefinitionToInputSchema($definition, $pluginId);

    return new ToolInfo(
      name: $name,
      label: $this->normalizeValue($definition->getLabel()),
      description: $this->normalizeValue($definition->getDescription()),
      inputSchema: $inputSchema,
      annotations: $annotations,
      provider: (string) ($definition->getProvider() ?? ''),
      metadata: [
        'plugin_id' => $pluginId,
      ],
    );
  }

  /**
   * Determine whether a tool definition should be exposed.
   */
  private function isToolAllowed(ToolDefinition $definition): bool {
    if (!$this->includeAllTools) {
      $provider = $definition->getProvider() ?? '';
      if (!is_string($provider) || !str_starts_with($provider, $this->allowedProviderPrefix)) {
        return FALSE;
      }
    }

    // What the session may not call is not offered for discovery either.
    return $this->accessManager === NULL || $this->accessManager->listsTool($definition);
  }

  /**
   * Converts MCP tool names back to Tool API plugin IDs.
   */
  private function mcpNameToPluginId(string $toolName): string {
    return str_replace('___', ':', $toolName);
  }

  /**
   * Normalizes a value to a string.
   */
  private function normalizeValue(mixed $value): string {
    if ($value instanceof TranslatableMarkup) {
      return (string) $value;
    }

    if (is_object($value) && method_exists($value, '__toString')) {
      return (string) $value;
    }

    if (is_scalar($value)) {
      return (string) $value;
    }

    return '';
  }

  /**
   * Normalizes an array for structured output.
   *
   * @param array<string, mixed> $data
   *   The data to normalize.
   *
   * @return array<string, mixed>
   *   The normalized data.
   */
  private function normalizeArray(array $data): array {
    $normalized = [];
    foreach ($data as $key => $value) {
      if ($value instanceof TranslatableMarkup) {
        $normalized[$key] = (string) $value;
      }
      elseif (is_array($value)) {
        $normalized[$key] = $this->normalizeArray($value);
      }
      elseif (is_object($value)) {
        if ($value instanceof \JsonSerializable) {
          $normalized[$key] = $value->jsonSerialize();
        }
        elseif (method_exists($value, '__toString')) {
          $normalized[$key] = (string) $value;
        }
        else {
          $normalized[$key] = get_class($value);
        }
      }
      else {
        $normalized[$key] = $value;
      }
    }
    return $normalized;
  }

  /**
   * Clears the tool cache.
   *
   * Call this when tool definitions may have changed.
   */
  public function clearCache(): void {
    $this->toolCache = NULL;
  }

}
