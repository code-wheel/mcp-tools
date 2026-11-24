<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Tool\ToolDefinition;
use CodeWheel\McpEvents\ToolExecutionFailedEvent;
use CodeWheel\McpEvents\ToolExecutionStartedEvent;
use CodeWheel\McpEvents\ToolExecutionSucceededEvent;
use Drupal\mcp_tools\Mcp\Error\DefaultToolErrorHandler;
use Drupal\mcp_tools\Mcp\Error\ToolErrorHandlerInterface;
use Drupal\mcp_tools\Policy\ToolPolicy;
use Drupal\mcp_tools\Policy\ToolRefusal;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\McpClientBridge;
use Drupal\mcp_tools\Service\McpToolCallContext;
use Drupal\mcp_tools\Tool\AsksFirstInterface;
use Drupal\tool\FailureCategory;
use Drupal\tool\Tool\Invoker;
use Drupal\tool\Tool\InvokerCapability;
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
    private readonly ?AccessManager $accessManager = NULL,
    private readonly ?McpToolCallContext $toolCallContext = NULL,
  ) {}

  /**
   * What runs a tool: Tool API's runner, with the site's rules around it.
   *
   * Taken from the container for each call and not kept. Other code builds
   * this handler too (MCPIO, tests) with the arguments it knew, and a long
   * STDIO session outlives the container it started with when a module is
   * installed along the way.
   */
  private function policy(): ToolPolicy {
    return \Drupal::service('mcp_tools.tool_policy');
  }

  /**
   * Who is calling, as Tool API passes it to the tool.
   *
   * A tool can decide by its caller. Without an invoker a call looks like
   * code of the site itself. Tool API is adding a "remote" capability for
   * callers outside the site (#3583130): it is passed as soon as the
   * installed Tool API has it.
   */
  private static function invoker(): Invoker {
    $remote = InvokerCapability::class . '::Remote';
    return defined($remote) ? new Invoker('mcp_tools', constant($remote)) : new Invoker('mcp_tools');
  }

  /**
   * {@inheritdoc}
   */
  public function supports(Request $request): bool {
    return $request instanceof CallToolRequest;
  }

  /**
   * {@inheritdoc}
   *
   * The call runs in a Fiber of its own, so Drupal's Fiber suspensions do
   * not reach the SDK. See FiberIsolation.
   */
  public function handle(Request $request, SessionInterface $session): Response|Error {
    return FiberIsolation::run(function () use ($request, $session): Response|Error {
      // The call starts like a new request before anything is checked, so
      // the access check reads the settings as they are now. A tool's own
      // enter() inside this one resets nothing a second time.
      $this->toolCallContext?->enter();
      try {
        return $this->doHandle($request, $session);
      }
      finally {
        $this->toolCallContext?->leave();
      }
    });
  }

  /**
   * Converts the JSON objects in tool arguments to arrays.
   *
   * @param mixed $value
   *   The arguments, or a value inside them.
   * @param bool $keepEmptyObjects
   *   Leave an empty object as it is, so it still reads as an object.
   *
   * @return mixed
   *   The same value with \stdClass objects turned into arrays.
   */
  private static function objectsToArrays(mixed $value, bool $keepEmptyObjects = FALSE): mixed {
    if ($value instanceof \stdClass) {
      $properties = get_object_vars($value);
      if ($properties === [] && $keepEmptyObjects) {
        return $value;
      }
      $value = $properties;
    }
    if (is_array($value)) {
      foreach ($value as $key => $item) {
        $value[$key] = self::objectsToArrays($item, $keepEmptyObjects);
      }
    }
    return $value;
  }

  /**
   * Handles a tools/call request.
   */
  private function doHandle(Request $request, SessionInterface $session): Response|Error {
    assert($request instanceof CallToolRequest);

    $startedAt = microtime(TRUE);
    $requestId = $request->getId();
    $toolName = $request->name;
    // Up to mcp/sdk 0.9 the SDK decoded every JSON object in the arguments
    // to a PHP array. From 0.10 it keeps nested objects as \stdClass, so
    // that {} and [] stay apart. Tool API inputs and the tools work with
    // arrays, so that is what they get. The input check below gets a copy
    // that keeps empty objects: an empty PHP array would read as a JSON
    // list and be refused for an input that is a map.
    $rawArguments = $request->arguments ?? [];
    $arguments = self::objectsToArrays($rawArguments);
    $sanitizedArguments = $this->sanitizeArguments($arguments);

    $pluginId = $this->mcpNameToPluginId($toolName);

    $definitions = $this->toolManager->getDefinitions();
    $definition = $definitions[$pluginId] ?? NULL;

    // A name with only "tools_" left out (mcp_search_content) is the tool
    // with it, and one with "tools_" put in (mcp_tools_redirect_create) the
    // tool without it. From here on the call is one by the full name: the same
    // checks, limits and audit line.
    $meant = $definition instanceof ToolDefinition ? NULL : ToolNameSuggester::meantAmong($toolName, array_map(static fn($id): string => McpToolsServerFactory::pluginIdToMcpName((string) $id), array_keys($definitions)));
    if ($meant !== NULL && ($definitions[$this->mcpNameToPluginId($meant)] ?? NULL) instanceof ToolDefinition) {
      $toolName = $meant;
      $pluginId = $this->mcpNameToPluginId($meant);
      $definition = $definitions[$pluginId];
    }

    if (!$definition instanceof ToolDefinition) {
      return $this->unknownTool($request->name, $definitions, $request->getId());
    }

    $provider = $definition->getProvider() ?? '';
    if (!$this->includeAllTools && (!is_string($provider) || !str_starts_with($provider, $this->allowedProviderPrefix))) {
      return $this->unknownTool($request->name, $definitions, $request->getId());
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
    $normalizedArguments = $this->normalizeArgumentsForValidation($inputDefinitions, self::objectsToArrays($rawArguments, TRUE));
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
      return $this->executeCall($toolName, $pluginId, $definition, $arguments, $sanitizedArguments, $toolErrorHandler, $startedAt, $requestId);
    }
    finally {
      $this->clientBridge?->setGateway(NULL);
    }
  }

  /**
   * Runs the tool through Tool API's runner and builds the MCP response.
   *
   * The runner checks the declared permission, sets and validates the
   * inputs, asks the tool for access and executes it. The site's rules for
   * a tool of another provider are around it, in ToolPolicy.
   *
   * @param string $toolName
   *   The wire-format tool name.
   * @param string $pluginId
   *   The Tool API plugin ID.
   * @param \Drupal\tool\Tool\ToolDefinition $definition
   *   The tool's definition.
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
  private function executeCall(string $toolName, string $pluginId, ToolDefinition $definition, array $arguments, array $sanitizedArguments, ToolErrorHandlerInterface $toolErrorHandler, float $startedAt, mixed $requestId): Response {
    $failed = function (string $reason, CallToolResult $result, ?\Throwable $exception = NULL) use ($toolName, $pluginId, $sanitizedArguments, $startedAt, $requestId): Response {
      $this->dispatchEvent(new ToolExecutionFailedEvent(
        $toolName,
        $pluginId,
        $sanitizedArguments,
        $reason,
        $result,
        $exception,
        $this->calculateDurationMs($startedAt),
        $requestId,
      ));
      return new Response($requestId, $result);
    };

    // The values, with best-effort upcasting.
    $values = [];
    foreach ($definition->getInputDefinitions() as $name => $inputDefinition) {
      if (!array_key_exists($name, $arguments)) {
        continue;
      }
      $values[$name] = $inputDefinition instanceof InputDefinitionInterface
        ? $this->upcastArgument($arguments[$name], $inputDefinition)
        : $arguments[$name];
    }

    // The site's rules apply when this server was built with them. Another
    // module that builds this handler for its own server brings its own.
    try {
      $outcome = $this->policy()->run($definition, $values, self::invoker(), $this->accessManager !== NULL);
    }
    catch (PluginException $e) {
      return $failed(ToolExecutionFailedEvent::REASON_INSTANTIATION, $toolErrorHandler->instantiationFailed($pluginId, $e), $e);
    }
    catch (\Throwable $e) {
      // Tool API turns what a tool throws while it runs into a failed
      // result. What gets here was thrown around that, by the tool's own
      // access check for one.
      return $failed(ToolExecutionFailedEvent::REASON_EXECUTION, $toolErrorHandler->executionFailed($toolName, $e), $e);
    }

    if ($outcome instanceof ToolRefusal) {
      if ($outcome->reason === ToolRefusal::ACCESS) {
        return $failed(ToolExecutionFailedEvent::REASON_ACCESS_DENIED, $toolErrorHandler->accessDenied($toolName));
      }
      if ($outcome->reason === ToolRefusal::CONFIRMATION) {
        return $this->confirmationRefusal($requestId, $toolName, $outcome->message);
      }
      return $failed(ToolExecutionFailedEvent::REASON_POLICY_BUDGET, new CallToolResult([new TextContent($outcome->message)], TRUE, [
        'success' => FALSE,
        'message' => $outcome->message,
        'error' => $outcome->message,
        'error_code' => $outcome->code,
        'tool' => $toolName,
        'retry_after' => $outcome->retryAfter,
        'remediation' => 'Wait for the number of seconds in retry_after, then call again.',
      ]));
    }

    $result = $outcome->getResult();
    $message = (string) $result->getMessage();

    // Refused by Tool API before the tool ran: by its declared permission,
    // by its own access check, or for an input Tool API could not take.
    if (!$outcome->hasExecuted()) {
      if ($result->getFailureCategory() === FailureCategory::Access) {
        return $failed(ToolExecutionFailedEvent::REASON_ACCESS_DENIED, $toolErrorHandler->accessDenied($toolName));
      }
      $message = $message !== '' ? $message : 'Invalid input.';
      return $failed(ToolExecutionFailedEvent::REASON_VALIDATION, new CallToolResult([new TextContent($message)], TRUE, [
        'success' => FALSE,
        'message' => $message,
        'data' => [],
      ]));
    }

    if ($message === '') {
      $message = $result->isSuccess() ? 'Success.' : 'Tool execution failed.';
    }

    // A failed tool has no outputs. A tool that worked has its declared
    // outputs, each present, and what it returned when it declares none.
    // The values are the tool's own, not Tool API's formatted ones: those
    // follow the schema of Tool API's serializer, and this server
    // advertises the schema of ToolApiSchemaConverter.
    $output = [];
    if ($result->isSuccess()) {
      $context = $result->getContextValues();
      $declared = $definition->getOutputDefinitions();
      if ($declared === []) {
        $output = $context;
      }
      else {
        foreach (array_keys($declared) as $name) {
          $output[$name] = $context[$name] ?? NULL;
        }
      }
      $output = $this->normalizeValue($output);
      if (is_array($output)) {
        $output = self::emptyMapsAsObjects($output, $declared);
      }
    }

    $structured = [
      'success' => $result->isSuccess(),
      'message' => $message,
      'data' => $output,
    ];

    $text = $message;
    if (!empty($output)) {
      $text .= "\n" . json_encode($structured, JSON_PRETTY_PRINT);
    }

    $callToolResult = new CallToolResult([new TextContent($text)], !$result->isSuccess(), $structured);
    if (!$result->isSuccess()) {
      return $failed(ToolExecutionFailedEvent::REASON_RESULT, $callToolResult);
    }
    $this->dispatchEvent(new ToolExecutionSucceededEvent(
      $toolName,
      $pluginId,
      $sanitizedArguments,
      $callToolResult,
      $this->calculateDurationMs($startedAt),
      $requestId,
    ));
    return new Response($requestId, $callToolResult);
  }

  /**
   * Converts MCP tool names back to Tool API plugin IDs.
   */
  private function mcpNameToPluginId(string $toolName): string {
    return str_replace('___', ':', $toolName);
  }

  /**
   * The answer to a tool name that does not exist: with the closest names.
   *
   * Only names this session is shown are offered: tools of the providers
   * this server serves, which the session may call.
   *
   * @param string $toolName
   *   The name that was used.
   * @param array $definitions
   *   All tool definitions, by plugin ID.
   * @param string|int $requestId
   *   The ID of the request.
   */
  private function unknownTool(string $toolName, array $definitions, string|int $requestId): Error {
    $names = [];
    foreach ($definitions as $pluginId => $definition) {
      if (!$definition instanceof ToolDefinition) {
        continue;
      }
      $provider = $definition->getProvider() ?? '';
      if (!$this->includeAllTools && (!is_string($provider) || !str_starts_with($provider, $this->allowedProviderPrefix))) {
        continue;
      }
      if ($this->accessManager !== NULL && !$this->accessManager->listsTool($definition)) {
        continue;
      }
      $names[] = McpToolsServerFactory::pluginIdToMcpName((string) $pluginId);
    }

    $closest = ToolNameSuggester::closest($toolName, $names);
    $message = "Unknown tool: {$toolName}";
    if ($closest !== []) {
      $message .= '. Did you mean ' . implode(', ', $closest) . '?';
    }
    return Error::forMethodNotFound($message, $requestId);
  }

  /**
   * Best-effort upcasting of input arguments based on input definition.
   */
  private function upcastArgument(mixed $argument, InputDefinitionInterface $definition): mixed {
    $dataType = $definition->getDataType();

    // An object or a list sent as its JSON text is meant as the object or
    // the list. nemotron-3.5-lightning sent "fields" and "updates" that way
    // (37 refused calls in four runs), mimo-v2.6 a permission list, which
    // then read as one permission called ["access content"].
    $wantsStructure = $definition instanceof ListInputDefinition
      || $definition instanceof MapInputDefinition
      || $definition->isMultiple()
      || $dataType === 'list'
      || $dataType === 'map';
    if ($wantsStructure && is_string($argument) && preg_match('/^\s*[\[{]/', $argument)) {
      $decoded = json_decode($argument, TRUE);
      if (is_array($decoded)) {
        $argument = $decoded;
      }
    }

    if ($definition instanceof ListInputDefinition || $definition->isMultiple() || $dataType === 'list') {
      if (!is_array($argument) && $argument !== NULL && $argument !== '') {
        $argument = [$argument];
      }

      if ($definition instanceof ListInputDefinition && is_array($argument)) {
        // getItemDefinition() is on the list definition itself in every
        // Tool API version we support (1.0.0-beta12 and later).
        $itemDefinition = $definition->getItemDefinition();
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

    // A yes/no or a whole number is cast only when its meaning is plain.
    // Anything else stays as sent, and the schema check refuses it: it was
    // made FALSE ("garbage", 2, "1.0") or cut to a whole number ("1.9" to 1,
    // "1e3" to 1000), and the call went on with a value nobody sent.
    if ($dataType === 'boolean') {
      if (is_bool($argument)) {
        return $argument;
      }
      if (is_int($argument) || is_string($argument)) {
        return filter_var($argument, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $argument;
      }
      return $argument;
    }

    if ($dataType === 'integer') {
      if (is_string($argument) && preg_match('/^\s*-?\d+\s*$/', $argument)) {
        return (int) $argument;
      }
      if (is_float($argument) && floor($argument) === $argument && abs($argument) <= PHP_INT_MAX) {
        return (int) $argument;
      }
      return $argument;
    }

    if ($dataType === 'float') {
      return is_numeric($argument) ? (float) $argument : $argument;
    }

    // A number or a yes/no given to a text input is meant as its text: a
    // model sent status: 1 for "published", and the input check refused
    // it before the tool could read it.
    if ($dataType === 'string' && (is_int($argument) || is_float($argument))) {
      return (string) $argument;
    }
    if ($dataType === 'string' && is_bool($argument)) {
      return $argument ? 'true' : 'false';
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
   * Asks the client's user before a call that needs a person's yes.
   *
   * Two kinds of call need one. A tool that declares itself destructive,
   * of MCP Tools or of another module. And a tool of MCP Tools that says so
   * for this call (AsksFirstInterface): granting a permission for trusted
   * roles only, applying a recipe no person has read.
   *
   * This method was written to leave MCP Tools' own destructive tools out,
   * "because they take a confirm flag". It named their base class without
   * importing it, so it never left one out, and only one of them has such a
   * flag. What it did is the safer rule, and is now what it says.
   *
   * Such a call runs after the client's user said yes, where the client can
   * ask. A client that cannot ask gets it run as before, unless the site
   * insists on a confirmation (access.confirm_destructive_tools): then it
   * is refused.
   *
   * Tool API also calls a write tool destructive when it declares nothing.
   * Going by that, nearly every write would be asked about, so only what
   * the tool says of itself counts here.
   *
   * @param string $pluginId
   *   The Tool API plugin ID.
   * @param array<string, mixed> $arguments
   *   The call arguments.
   * @param \Mcp\Server\Session\SessionInterface $session
   *   The MCP session, for the client's capabilities.
   * @param mixed $requestId
   *   The JSON-RPC request id, for the refusal or the declined answer.
   *
   * @return array<string, mixed>|\Mcp\Schema\JsonRpc\Response
   *   The arguments when the call may go on, or the answer that ends it.
   */
  private function maybeAskFirst(string $pluginId, array $arguments, SessionInterface $session, mixed $requestId): array|Response {
    $definition = $this->toolManager->getDefinitions()[$pluginId] ?? NULL;
    if (!$definition instanceof ToolDefinition) {
      return $arguments;
    }
    $class = $definition->getClass();
    $asksItself = is_string($class) && is_a($class, AsksFirstInterface::class, TRUE);
    $destructive = $definition->get('destructive') === TRUE;
    if (!$asksItself && !$destructive) {
      return $arguments;
    }

    $canAsk = class_exists(ElicitationSchema::class)
      && \Fiber::getCurrent() !== NULL
      && (new ClientGateway($session))->supportsElicitation();
    $siteInsists = (bool) $this->accessManager?->destructiveToolsNeedConfirmation();
    if (!$canAsk && !$siteInsists) {
      return $arguments;
    }

    // Ask, or refuse for want of an answer, only a session that may run the
    // tool. Any other is refused at the door, as for every tool.
    if (!$this->sessionMayRun($pluginId)) {
      return $arguments;
    }

    $toolName = McpToolsServerFactory::pluginIdToMcpName($pluginId);
    $reason = $asksItself ? $this->reasonToAsk($pluginId, $arguments) : NULL;
    if ($reason === NULL && !$destructive) {
      return $arguments;
    }
    if ($reason !== NULL) {
      $message = $reason . ' Run it?';
      $cannotAsk = $reason . ' This site runs such a call only after the user confirmed it. This client cannot ask its user, so nothing was run. No argument changes that: do not call it again. Tell the user what you wanted to run, so a person can do it or allow it.';
      $noAnswer = $reason . ' The user was asked to confirm and no answer came back, so nothing was run.';
    }
    else {
      $message = sprintf('The tool "%s" (%s, from the module %s) says it is destructive. Run it?', (string) $definition->getLabel(), $toolName, (string) $definition->getProvider());
      $shown = $this->sanitizeArguments($arguments);
      if ($shown !== []) {
        $message .= ' Arguments: ' . mb_substr((string) json_encode($shown, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 600);
      }
      $cannotAsk = "The tool '$toolName' says it is destructive, and this site runs such a tool only after the user confirmed it. This client cannot ask its user, so the tool was not run. No argument changes that: do not call it again. Tell the user what you wanted to run, so a person can do it or allow it.";
      $noAnswer = "The tool '$toolName' says it is destructive. The user was asked to confirm and no answer came back, so the tool was not run.";
    }

    if (!$canAsk) {
      return $this->confirmationRefusal($requestId, $toolName, $cannotAsk);
    }

    try {
      $result = (new ClientGateway($session))->elicit($message, new ElicitationSchema(
        properties: [
          'confirm' => new BooleanSchemaDefinition('Run it', 'Choose yes to run the tool.'),
        ],
        required: ['confirm'],
      ), 300);
    }
    catch (\Throwable $e) {
      // The question was put and no answer came back. That is not a yes.
      $this->logger->warning('Elicitation failed for @plugin: @message', [
        '@plugin' => $pluginId,
        '@message' => $e->getMessage(),
      ]);
      return $this->confirmationRefusal($requestId, $toolName, $noAnswer);
    }

    if ($result->action === ElicitAction::Accept && !empty($result->content['confirm'])) {
      // The audit lines of this call say that a person said yes.
      $this->toolCallContext?->markConfirmedByUser();
      return $arguments;
    }

    $text = 'Declined by the user. Nothing was run.';
    return new Response($requestId, new CallToolResult(
      [new TextContent($text)],
      FALSE,
      ['success' => FALSE, 'message' => $text, 'data' => []],
    ));
  }

  /**
   * Why one of our own tools wants a yes for this call, if it does.
   */
  private function reasonToAsk(string $pluginId, array $arguments): ?string {
    try {
      $tool = $this->toolManager->createInstance($pluginId);
      return $tool instanceof AsksFirstInterface ? $tool->reasonToAsk($arguments) : NULL;
    }
    catch (\Throwable $e) {
      // The call itself will say what is wrong with the tool.
      $this->logger->warning('Could not ask @plugin whether it needs a confirmation: @message', [
        '@plugin' => $pluginId,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * The answer to a destructive call that could not be confirmed.
   */
  private function confirmationRefusal(mixed $requestId, string $toolName, string $message): Response {
    return new Response($requestId, new CallToolResult([new TextContent($message)], TRUE, [
      'success' => FALSE,
      'message' => $message,
      'error' => $message,
      'error_code' => 'CONFIRMATION_REQUIRED',
      'tool' => $toolName,
    ]));
  }

  /**
   * Whether this session could run a tool at all, asked before any question.
   *
   * The user of a session that may not run a tool is not asked to confirm
   * it. With the access manager this is the rule the tool list follows,
   * asked without making the tool. A handler built without one (a test,
   * another module) asks the tool itself, the way it was done before Tool
   * API's runner: that call leaves when Tool API has a way to ask without
   * an instance.
   */
  private function sessionMayRun(string $pluginId): bool {
    $definition = $this->toolManager->getDefinitions()[$pluginId] ?? NULL;
    if (!$definition instanceof ToolDefinition) {
      return FALSE;
    }
    if ($this->accessManager !== NULL) {
      return $this->accessManager->couldCall($definition);
    }
    try {
      return (bool) $this->toolManager->createInstance($pluginId)->access();
    }
    catch (\Throwable) {
      return FALSE;
    }
  }

  /**
   * Sends an empty map as {} and not as [].
   *
   * PHP has one empty array for both; JSON does not. A client that checks
   * the result against the outputSchema rejects [] for an output declared
   * as a map, and the model gets a schema error in place of the result.
   *
   * @param array<string, mixed> $output
   *   The output values, keyed by output name.
   * @param array<string, object> $definitions
   *   The tool's output definitions.
   *
   * @return array<string, mixed>
   *   The same values, empty maps as objects.
   */
  private static function emptyMapsAsObjects(array $output, array $definitions): array {
    foreach ($definitions as $name => $definition) {
      if (($output[$name] ?? NULL) === []
        && method_exists($definition, 'getDataType')
        && (string) $definition->getDataType() === 'map') {
        $output[$name] = new \stdClass();
      }
    }
    return $output;
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
    if ($confirmation === NULL) {
      return $this->maybeAskFirst($pluginId, $arguments, $session, $requestId);
    }
    if (!empty($arguments[$confirmation['confirm_argument']])) {
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

    // Ask only a session that may run the tool. The question used to come
    // before the access check, so the user of a read-only session was asked
    // to confirm a deletion that was then refused.
    if (!$this->sessionMayRun($pluginId)) {
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
      $this->toolCallContext?->markConfirmedByUser();
      return $arguments;
    }

    $text = 'Deletion declined by the user. Nothing was deleted.';
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
