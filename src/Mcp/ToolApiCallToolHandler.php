<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolInterface;
use Drupal\tool\TypedData\InputDefinitionInterface;
use Drupal\tool\TypedData\ListInputDefinition;
use Drupal\tool\TypedData\MapInputDefinition;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\LoggerInterface;

/**
 * Executes Drupal Tool API tools from MCP tool call requests.
 *
 * This bypasses the SDK's reflection-based parameter mapping and instead passes
 * arguments as an associative array to Tool API plugins.
 *
 * @implements \Mcp\Server\Handler\Request\RequestHandlerInterface<\Mcp\Schema\Result\CallToolResult>
 */
final class ToolApiCallToolHandler implements RequestHandlerInterface {

  public function __construct(
    private readonly PluginManagerInterface $toolManager,
    private readonly LoggerInterface $logger,
    private readonly bool $includeAllTools = FALSE,
    private readonly string $allowedProviderPrefix = 'mcp_tools',
  ) {}

  /**
   * {@inheritdoc}
   */
  public function supports(Request $request): bool {
    return $request instanceof CallToolRequest;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, SessionInterface $session): Response|Error {
    assert($request instanceof CallToolRequest);

    $toolName = $request->name;
    $arguments = $request->arguments ?? [];

    $pluginId = $this->mcpNameToPluginId($toolName);

    $definitions = $this->toolManager->getDefinitions();
    $definition = $definitions[$pluginId] ?? NULL;

    if (!$definition instanceof ToolDefinition) {
      return Error::forMethodNotFound("Unknown tool: {$toolName}", $request->getId());
    }

    $provider = $definition->getProvider() ?? '';
    if (!$this->includeAllTools && (!is_string($provider) || !str_starts_with($provider, $this->allowedProviderPrefix))) {
      return Error::forMethodNotFound("Unknown tool: {$toolName}", $request->getId());
    }

    try {
      $tool = $this->toolManager->createInstance($pluginId);
    }
    catch (\Throwable $e) {
      $this->logger->error('Failed to create tool instance: @tool | @message', [
        '@tool' => $pluginId,
        '@message' => $e->getMessage(),
      ]);

      $content = [new TextContent('Tool instantiation failed: ' . $e->getMessage())];
      return new Response($request->getId(), CallToolResult::error($content));
    }

    if (!$tool instanceof ToolInterface) {
      $content = [new TextContent("Tool plugin {$pluginId} does not implement ToolInterface.")];
      return new Response($request->getId(), CallToolResult::error($content));
    }

    // Set input values with best-effort upcasting.
    foreach ($tool->getInputDefinitions() as $name => $inputDefinition) {
      if (!array_key_exists($name, $arguments)) {
        continue;
      }
      $value = $arguments[$name];
      if ($inputDefinition instanceof InputDefinitionInterface) {
        $value = $this->upcastArgument($value, $inputDefinition);
      }
      $tool->setInputValue($name, $value);
    }

    // Check access.
    if (!$tool->access()) {
      $structured = [
        'success' => FALSE,
        'error' => 'Access denied.',
        'tool' => $toolName,
      ];
      $content = [new TextContent('Access denied.')];
      return new Response($request->getId(), new CallToolResult($content, TRUE, $structured));
    }

    try {
      $tool->execute();
      $result = $tool->getResult();

      $message = (string) $result->getMessage();
      if ($message === '') {
        $message = $result->isSuccess() ? 'Success.' : 'Tool execution failed.';
      }

      $output = [];
      if ($toolOutput = $tool->getOutputValues()) {
        $output = $toolOutput;
      }
      elseif ($contextValues = $result->getContextValues()) {
        $output = $contextValues;
      }

      $output = $this->normalizeValue($output);

      $structured = [
        'success' => $result->isSuccess(),
        'message' => $message,
        'data' => $output,
      ];

      $text = $message;
      if (!empty($output)) {
        $text .= "\n" . json_encode($structured, JSON_PRETTY_PRINT);
      }

      $content = [new TextContent($text)];
      return new Response($request->getId(), new CallToolResult($content, !$result->isSuccess(), $structured));
    }
    catch (\Throwable $e) {
      $this->logger->error('Tool execution failed: @tool | @message', [
        '@tool' => $pluginId,
        '@message' => $e->getMessage(),
      ]);

      $structured = [
        'success' => FALSE,
        'error' => $e->getMessage(),
        'tool' => $toolName,
      ];
      $content = [new TextContent('Tool execution failed: ' . $e->getMessage())];
      return new Response($request->getId(), new CallToolResult($content, TRUE, $structured));
    }
  }

  /**
   * Converts MCP tool names back to Tool API plugin IDs.
   */
  private function mcpNameToPluginId(string $toolName): string {
    return str_replace('___', ':', $toolName);
  }

  /**
   * Best-effort upcasting of input arguments based on input definition.
   */
  private function upcastArgument(mixed $argument, InputDefinitionInterface $definition): mixed {
    $dataType = $definition->getDataType();

    if ($definition instanceof ListInputDefinition || $definition->isMultiple() || $dataType === 'list') {
      if (!is_array($argument) && $argument !== NULL && $argument !== '') {
        $argument = [$argument];
      }

      if ($definition instanceof ListInputDefinition && is_array($argument)) {
        $itemDefinition = $definition->getDataDefinition()->getItemDefinition();
        if ($itemDefinition instanceof InputDefinitionInterface) {
          foreach ($argument as $key => $value) {
            $argument[$key] = $this->upcastArgument($value, $itemDefinition);
          }
        }
      }

      return $argument;
    }

    if ($definition instanceof MapInputDefinition && is_array($argument)) {
      foreach ($definition->getPropertyDefinitions() as $propertyName => $propertyDefinition) {
        if (array_key_exists($propertyName, $argument) && $propertyDefinition instanceof InputDefinitionInterface) {
          $argument[$propertyName] = $this->upcastArgument($argument[$propertyName], $propertyDefinition);
        }
      }
      return $argument;
    }

    if ($dataType === 'boolean') {
      return filter_var($argument, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? FALSE;
    }

    if ($dataType === 'integer') {
      return is_numeric($argument) ? (int) $argument : $argument;
    }

    if ($dataType === 'float') {
      return is_numeric($argument) ? (float) $argument : $argument;
    }

    return $argument;
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

}
