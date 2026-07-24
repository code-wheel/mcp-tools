<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Tool\ToolDefinition;
use CodeWheel\McpEvents\ToolExecutionFailedEvent;
use CodeWheel\McpEvents\ToolExecutionStartedEvent;
use CodeWheel\McpEvents\ToolExecutionSucceededEvent;
use Drupal\mcp_tools\Mcp\Error\DefaultToolErrorHandler;
use Drupal\mcp_tools\Mcp\Error\ToolErrorHandlerInterface;
use Drupal\mcp_tools\Service\McpClientBridge;
use Drupal\tool\Tool\ToolInterface;
use Drupal\tool\TypedData\InputDefinitionInterface;
use Drupal\tool\TypedData\ListInputDefinition;
use Drupal\tool\TypedData\MapInputDefinition;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Elicitation\BooleanSchemaDefinition;
use Mcp\Schema\Elicitation\ElicitationSchema;
use Mcp\Schema\Enum\ElicitAction;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Executes Drupal Tool API tools from MCP tool call requests.
 *
 * This bypasses the SDK's reflection-based parameter mapping and instead passes
 * arguments as an associative array to Tool API plugins.
 *
 * @implements \Mcp\Server\Handler\Request\RequestHandlerInterface<\Mcp\Schema\Result\CallToolResult>
 */
class ToolApiCallToolHandler implements RequestHandlerInterface {

  public function __construct(
    private readonly PluginManagerInterface $toolManager,
    private readonly LoggerInterface $logger,
    private readonly bool $includeAllTools = FALSE,
    private readonly string $allowedProviderPrefix = 'mcp_tools',
    private readonly ?ToolInputValidator $inputValidator = NULL,
    private readonly ?ToolErrorHandlerInterface $errorHandler = NULL,
    private readonly ?EventDispatcherInterface $eventDispatcher = NULL,
    private readonly ?McpClientBridge $clientBridge = NULL,
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

    $startedAt = microtime(TRUE);
    $requestId = $request->getId();
    $toolName = $request->name;
    $arguments = $request->arguments ?? [];
    $sanitizedArguments = $this->sanitizeArguments($arguments);

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

    $this->dispatchEvent(new ToolExecutionStartedEvent(
      $toolName,
      $pluginId,
      $sanitizedArguments,
      $requestId,
      $startedAt,
    ));

    $toolErrorHandler = $this->errorHandler ?? new DefaultToolErrorHandler($this->logger);
    $validator = $this->inputValidator ?? new ToolInputValidator(new ToolApiSchemaConverter(), $this->logger);
    $inputDefinitions = $definition->getInputDefinitions();
    $normalizedArguments = $this->normalizeArgumentsForValidation($inputDefinitions, $arguments);
    // Tools without declared inputs must still reject stray arguments —
    // their schema declares additionalProperties: false too.
    if (!empty($inputDefinitions) || !empty($normalizedArguments)) {
      $validation = $validator->validate($definition, $normalizedArguments);
      if (!$validation['valid']) {
        $result = $toolErrorHandler->validationFailed($toolName, $validation['errors']);
        $this->dispatchEvent(new ToolExecutionFailedEvent(
          $toolName,
          $pluginId,
          $sanitizedArguments,
          ToolExecutionFailedEvent::REASON_VALIDATION,
          $result,
          NULL,
          $this->calculateDurationMs($startedAt),
          $requestId,
        ));
        return new Response($requestId, $result);
      }
    }

    // Elicitation-based destructive confirmation: when the client can ask
    // its human, a bare destructive call elicits a native confirmation
    // instead of returning the refusal round-trip.
    $elicited = $this->maybeElicitDestructiveConfirmation($pluginId, $arguments, $session, $requestId);
    if ($elicited instanceof Response) {
      return $elicited;
    }
    $arguments = $elicited;

    $gateway = NULL;
    if (class_exists(ClientGateway::class) && \Fiber::getCurrent() !== NULL) {
      $gateway = new ClientGateway($session);
    }
    $this->clientBridge?->setGateway($gateway, (array) $session->get('client_capabilities', []));
    try {
      return $this->executeCall($toolName, $pluginId, $arguments, $sanitizedArguments, $toolErrorHandler, $startedAt, $requestId);
    }
    finally {
      $this->clientBridge?->setGateway(NULL);
    }
  }

  /**
   * Instantiates and executes the tool, building the MCP response.
   *
   * @param string $toolName
   *   The wire-format tool name.
   * @param string $pluginId
   *   The Tool API plugin ID.
   * @param array<string, mixed> $arguments
   *   Validated (and possibly confirmation-amended) arguments.
   * @param array<string, mixed> $sanitizedArguments
   *   Redacted arguments for observability payloads.
   * @param \Drupal\mcp_tools\Mcp\Error\ToolErrorHandlerInterface $toolErrorHandler
   *   The error handler.
   * @param float $startedAt
   *   Execution start time.
   * @param mixed $requestId
   *   The JSON-RPC request id.
   */
  private function executeCall(string $toolName, string $pluginId, array $arguments, array $sanitizedArguments, ToolErrorHandlerInterface $toolErrorHandler, float $startedAt, mixed $requestId): Response {
    try {
      $tool = $this->toolManager->createInstance($pluginId);
    }
    catch (\Throwable $e) {
      $result = $toolErrorHandler->instantiationFailed($pluginId, $e);
      $this->dispatchEvent(new ToolExecutionFailedEvent(
        $toolName,
        $pluginId,
        $sanitizedArguments,
        ToolExecutionFailedEvent::REASON_INSTANTIATION,
        $result,
        $e,
        $this->calculateDurationMs($startedAt),
        $requestId,
      ));
      return new Response($requestId, $result);
    }

    if (!$tool instanceof ToolInterface) {
      $result = $toolErrorHandler->invalidTool($pluginId, "Tool plugin {$pluginId} does not implement ToolInterface.");
      $this->dispatchEvent(new ToolExecutionFailedEvent(
        $toolName,
        $pluginId,
        $sanitizedArguments,
        ToolExecutionFailedEvent::REASON_INVALID_TOOL,
        $result,
        NULL,
        $this->calculateDurationMs($startedAt),
        $requestId,
      ));
      return new Response($requestId, $result);
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
      $result = $toolErrorHandler->accessDenied($toolName);
      $this->dispatchEvent(new ToolExecutionFailedEvent(
        $toolName,
        $pluginId,
        $sanitizedArguments,
        ToolExecutionFailedEvent::REASON_ACCESS_DENIED,
        $result,
        NULL,
        $this->calculateDurationMs($startedAt),
        $requestId,
      ));
      return new Response($requestId, $result);
    }

    try {
      $tool->execute();
      $result = $tool->getResult();

      $message = (string) $result->getMessage();
      if ($message === '') {
        $message = $result->isSuccess() ? 'Success.' : 'Tool execution failed.';
      }

      $output = [];
      $toolOutput = NULL;
      try {
        $toolOutput = $tool->getOutputValues();
      }
      catch (\Throwable) {
        $toolOutput = NULL;
      }

      if (!empty($toolOutput)) {
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
      $callToolResult = new CallToolResult($content, !$result->isSuccess(), $structured);
      if ($result->isSuccess()) {
        $this->dispatchEvent(new ToolExecutionSucceededEvent(
          $toolName,
          $pluginId,
          $sanitizedArguments,
          $callToolResult,
          $this->calculateDurationMs($startedAt),
          $requestId,
        ));
      }
      else {
        $this->dispatchEvent(new ToolExecutionFailedEvent(
          $toolName,
          $pluginId,
          $sanitizedArguments,
          ToolExecutionFailedEvent::REASON_RESULT,
          $callToolResult,
          NULL,
          $this->calculateDurationMs($startedAt),
          $requestId,
        ));
      }
      return new Response($requestId, $callToolResult);
    }
    catch (\Throwable $e) {
      $result = $toolErrorHandler->executionFailed($toolName, $e);
      $this->dispatchEvent(new ToolExecutionFailedEvent(
        $toolName,
        $pluginId,
        $sanitizedArguments,
        ToolExecutionFailedEvent::REASON_EXECUTION,
        $result,
        $e,
        $this->calculateDurationMs($startedAt),
        $requestId,
      ));
      return new Response($requestId, $result);
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

  /**
   * Prepares arguments for validation by applying best-effort upcasting.
   *
   * @param array<string, \Drupal\tool\TypedData\InputDefinitionInterface> $inputDefinitions
   *   Tool input definitions.
   * @param array<string, mixed> $arguments
   *   Raw arguments.
   *
   * @return array<string, mixed>
   *   Normalized arguments.
   */
  private function normalizeArgumentsForValidation(array $inputDefinitions, array $arguments): array {
    $normalized = $arguments;
    foreach ($inputDefinitions as $name => $definition) {
      if (!array_key_exists($name, $normalized)) {
        continue;
      }
      $normalized[$name] = $this->upcastArgument($normalized[$name], $definition);
    }

    return $normalized;
  }

  /**
   * Destructive tools that elicit a user confirmation when possible.
   *
   * The confirm_argument is the boolean flag the tool requires for the
   * destructive default; when any bypass_argument is present the call is
   * not the destructive default and no confirmation is needed.
   *
   * @var array<string, array{confirm_argument: string, bypass_arguments: list<string>, message: string}>
   */
  private const CONFIRMATION_TOOLS = [
    'mcp_delete_content' => [
      'confirm_argument' => 'confirm_delete_all',
      'bypass_arguments' => ['language'],
      'message' => 'This will permanently delete node @nid including ALL of its language versions. Proceed?',
    ],
  ];

  /**
   * Elicits a native user confirmation for a bare destructive call.
   *
   * Only runs when the installed SDK supports elicitation, the request is
   * executing inside a Fiber (streaming transports), and the client
   * advertised the elicitation capability. In every other case the
   * arguments pass through unchanged and the tool's own refusal-based
   * guardrail still applies.
   *
   * @param string $pluginId
   *   The Tool API plugin ID.
   * @param array<string, mixed> $arguments
   *   The validated call arguments.
   * @param \Mcp\Server\Session\SessionInterface $session
   *   The MCP session.
   * @param mixed $requestId
   *   The JSON-RPC request id.
   *
   * @return array<string, mixed>|\Mcp\Schema\JsonRpc\Response
   *   The (possibly confirmed) arguments, or a declined-response.
   */
  private function maybeElicitDestructiveConfirmation(string $pluginId, array $arguments, SessionInterface $session, mixed $requestId): array|Response {
    $confirmation = self::CONFIRMATION_TOOLS[$pluginId] ?? NULL;
    if ($confirmation === NULL || !empty($arguments[$confirmation['confirm_argument']])) {
      return $arguments;
    }
    foreach ($confirmation['bypass_arguments'] as $bypass) {
      if (isset($arguments[$bypass]) && $arguments[$bypass] !== '') {
        return $arguments;
      }
    }
    if (!class_exists(ElicitationSchema::class) || \Fiber::getCurrent() === NULL) {
      return $arguments;
    }

    $gateway = new ClientGateway($session);
    if (!$gateway->supportsElicitation()) {
      return $arguments;
    }

    $message = strtr($confirmation['message'], ['@nid' => (string) ($arguments['nid'] ?? '?')]);
    try {
      $result = $gateway->elicit($message, new ElicitationSchema(
        properties: [
          'confirm' => new BooleanSchemaDefinition('Confirm deletion', 'Choose yes to delete everything.'),
        ],
        required: ['confirm'],
      ), 300);
    }
    catch (\Throwable $e) {
      // Client-side failure or timeout: fall back to the refusal-based flow.
      $this->logger->warning('Elicitation failed for @plugin: @message', [
        '@plugin' => $pluginId,
        '@message' => $e->getMessage(),
      ]);
      return $arguments;
    }

    if ($result->action === ElicitAction::Accept && !empty($result->content['confirm'])) {
      $arguments[$confirmation['confirm_argument']] = TRUE;
      return $arguments;
    }

    $text = 'Deletion declined by the user — nothing was deleted.';
    return new Response($requestId, new CallToolResult(
      [new TextContent($text)],
      FALSE,
      ['success' => FALSE, 'message' => $text, 'data' => []],
    ));
  }

  /**
   * Dispatches tool execution events without blocking tool execution.
   */
  private function dispatchEvent(object $event): void {
    if ($this->eventDispatcher === NULL) {
      return;
    }

    try {
      $this->eventDispatcher->dispatch($event);
    }
    catch (\Throwable $e) {
      $this->logger->error('Failed to dispatch MCP tool event: @event | @message', [
        '@event' => $event::class,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Redacts sensitive values from tool arguments for observability payloads.
   *
   * @param array<string, mixed> $arguments
   *   Raw tool arguments.
   *
   * @return array<string, mixed>
   *   Sanitized arguments.
   */
  private function sanitizeArguments(array $arguments): array {
    $sensitiveKeys = ['password', 'pass', 'secret', 'token', 'key', 'api_key', 'apikey'];
    $sanitized = $arguments;

    array_walk_recursive($sanitized, static function (&$value, $key) use ($sensitiveKeys): void {
      foreach ($sensitiveKeys as $sensitiveKey) {
        if (stripos((string) $key, $sensitiveKey) !== FALSE) {
          $value = '[REDACTED]';
          return;
        }
      }
    });

    return $sanitized;
  }

  /**
   * Converts a start timestamp into a duration in milliseconds.
   */
  private function calculateDurationMs(float $startedAt): float {
    return max(0.0, (microtime(TRUE) - $startedAt) * 1000.0);
  }

}
