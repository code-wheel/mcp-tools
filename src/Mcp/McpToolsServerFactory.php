<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\mcp_tools\Mcp\Resource\ResourceRegistry;
use Drupal\mcp_tools\Mcp\Prompt\PromptRegistry;
use Drupal\mcp_tools\Mcp\Error\ToolErrorHandlerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\McpClientBridge;
use Drupal\mcp_tools\Service\McpToolCallContext;
use Drupal\tool\Tool\ToolDefinition;
use Mcp\Schema\Annotations;
use Mcp\Schema\ServerCapabilities;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\Builder;
use Mcp\Server\Session\SessionStoreInterface;
use Mcp\Server\Wire\CachePolicy;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds an MCP Server instance that exposes Drupal Tool API tools.
 */
class McpToolsServerFactory {

  /**
   * How long a client may keep a list result, in milliseconds.
   */
  public const LIST_TTL_MS = 300000;

  public function __construct(
    private readonly PluginManagerInterface $toolManager,
    private readonly ToolApiSchemaConverter $schemaConverter,
    private readonly LoggerInterface $logger,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly ?ResourceRegistry $resourceRegistry = NULL,
    private readonly ?PromptRegistry $promptRegistry = NULL,
    private readonly ?ToolErrorHandlerInterface $toolErrorHandler = NULL,
    private readonly ?McpClientBridge $clientBridge = NULL,
    private readonly ?AccessManager $accessManager = NULL,
    private readonly ?McpToolCallContext $toolCallContext = NULL,
  ) {}

  /**
   * Creates a configured MCP server.
   *
   * @param string $serverName
   *   Server name.
   * @param string $serverVersion
   *   Server version.
   * @param int $paginationLimit
   *   Pagination limit for list operations.
   * @param bool $includeAllTools
   *   If TRUE, expose all Tool API tools. If FALSE, only expose tools whose
   *   provider starts with "mcp_tools".
   * @param \Mcp\Server\Session\SessionStoreInterface|null $sessionStore
   *   Optional session store (required for Streamable HTTP).
   * @param int $sessionTtl
   *   Session TTL in seconds.
   * @param bool $gatewayMode
   *   When TRUE, expose only gateway tools (discover/info/execute).
   * @param bool $enableResources
   *   When TRUE, register MCP resources.
   * @param bool $enablePrompts
   *   When TRUE, register MCP prompts.
   *
   * @return \Mcp\Server|\Mcp\Server\Server
   *   MCP server instance.
   */
  public function create(
    string $serverName,
    string $serverVersion,
    int $paginationLimit = 50,
    bool $includeAllTools = FALSE,
    ?SessionStoreInterface $sessionStore = NULL,
    int $sessionTtl = 3600,
    bool $gatewayMode = FALSE,
    bool $enableResources = TRUE,
    bool $enablePrompts = TRUE,
  ): object {
    $builder = Sdk::serverClass()::builder()
      ->setServerInfo($serverName, $serverVersion)
      ->setPaginationLimit($paginationLimit)
      // The SDK logs every registration at debug level, ~56 records per
      // request; only its warnings and errors belong in the site's logs.
      ->setLogger(new MinimumLevelLogger($this->logger))
      ->setEventDispatcher($this->eventDispatcher);

    if ($sessionStore) {
      // setSession()'s signature changed across mcp/sdk majors: 0.2.x–0.5.x
      // took a `ttl` argument; 0.6+ dropped it for GC-based expiry. Detect
      // the parameter so this module works across the supported SDK range.
      $accepts_ttl = FALSE;
      foreach ((new \ReflectionMethod($builder, 'setSession'))->getParameters() as $param) {
        if ($param->getName() === 'ttl') {
          $accepts_ttl = TRUE;
          break;
        }
      }
      if ($accepts_ttl) {
        $builder->setSession($sessionStore, ttl: $sessionTtl);
      }
      else {
        $builder->setSession($sessionStore);
      }
    }

    $resourceCount = $enableResources ? $this->addResources($builder) : 0;
    $promptCount = $enablePrompts ? $this->addPrompts($builder) : 0;
    $this->declareCapabilities($builder, $resourceCount > 0, $promptCount > 0);
    if (method_exists($builder, 'setInstructions')) {
      $builder->setInstructions(self::instructions($gatewayMode));
    }
    $this->declareCachePolicy($builder);

    if ($gatewayMode) {
      $gateway = new ToolApiGateway(
        $this->toolManager,
        $this->schemaConverter,
        $this->logger,
        $includeAllTools,
        'mcp_tools',
        $this->toolErrorHandler,
        $this->eventDispatcher,
        $this->accessManager,
        $this->toolCallContext,
      );

      foreach ($gateway->getGatewayTools() as $tool) {
        $builder->addTool(
          handler: $tool['handler'],
          name: $tool['name'],
          description: $tool['description'],
          annotations: $tool['annotations'],
          inputSchema: $tool['inputSchema'],
        );
      }

      return $builder->build();
    }

    // Intercept tool calls and execute Tool API tools directly.
    $builder->addRequestHandler(new ToolApiCallToolHandler(
      $this->toolManager,
      $this->logger,
      $includeAllTools,
      'mcp_tools',
      new ToolInputValidator($this->schemaConverter, $this->logger),
      $this->toolErrorHandler,
      $this->eventDispatcher,
      $this->clientBridge,
      $this->accessManager,
      $this->toolCallContext,
    ));

    $definitions = $this->toolManager->getDefinitions();
    foreach ($definitions as $pluginId => $definition) {
      if (!$definition instanceof ToolDefinition) {
        continue;
      }

      $provider = $definition->getProvider() ?? '';
      if (!$includeAllTools && (!is_string($provider) || !str_starts_with($provider, 'mcp_tools'))) {
        continue;
      }

      $annotationsData = $this->schemaConverter->toolDefinitionToAnnotations($definition, (string) $pluginId);
      $annotations = ToolAnnotations::fromArray($annotationsData);

      $outputSchema = self::builderSupportsOutputSchema()
        ? $this->schemaConverter->toolDefinitionToOutputSchema($definition, (string) $pluginId)
        : NULL;

      if ($outputSchema !== NULL) {
        $builder->addTool(
          handler: static fn() => NULL,
          name: self::pluginIdToMcpName((string) $pluginId),
          description: (string) $definition->getDescription(),
          annotations: $annotations,
          inputSchema: $this->schemaConverter->toolDefinitionToInputSchema($definition, (string) $pluginId),
          outputSchema: $outputSchema,
        );
      }
      else {
        $builder->addTool(
          handler: static fn() => NULL,
          name: self::pluginIdToMcpName((string) $pluginId),
          description: (string) $definition->getDescription(),
          annotations: $annotations,
          inputSchema: $this->schemaConverter->toolDefinitionToInputSchema($definition, (string) $pluginId),
        );
      }
    }

    return $builder->build();
  }

  /**
   * Whether the installed mcp/sdk Builder::addTool() accepts outputSchema.
   *
   * @var bool|null
   */
  private static ?bool $outputSchemaSupported = NULL;

  /**
   * Detects outputSchema support in the installed SDK (added in 0.6).
   */
  private static function builderSupportsOutputSchema(): bool {
    if (self::$outputSchemaSupported === NULL) {
      $supported = FALSE;
      foreach ((new \ReflectionMethod(Builder::class, 'addTool'))->getParameters() as $parameter) {
        if ($parameter->getName() === 'outputSchema') {
          $supported = TRUE;
          break;
        }
      }
      self::$outputSchemaSupported = $supported;
    }
    return self::$outputSchemaSupported;
  }

  /**
   * Registers resources and templates with the server builder.
   */
  private function addResources(Builder $builder): int {
    if ($this->resourceRegistry === NULL) {
      return 0;
    }
    $count = 0;

    $resources = $this->resourceRegistry->getResources();
    $seen = [];
    foreach ($resources as $resource) {
      if (empty($resource['uri']) || empty($resource['handler'])) {
        continue;
      }

      $uri = (string) $resource['uri'];
      if (isset($seen[$uri])) {
        $this->logger->warning('Skipping duplicate MCP resource URI: @uri', ['@uri' => $uri]);
        continue;
      }
      $seen[$uri] = TRUE;

      $annotations = NULL;
      if (!empty($resource['annotations']) && is_array($resource['annotations'])) {
        $annotations = Annotations::fromArray($resource['annotations']);
      }

      $builder->addResource(
        handler: $resource['handler'],
        uri: (string) $resource['uri'],
        name: $resource['name'] ?? NULL,
        description: $resource['description'] ?? NULL,
        mimeType: $resource['mimeType'] ?? NULL,
        size: $resource['size'] ?? NULL,
        annotations: $annotations,
        icons: $resource['icons'] ?? NULL,
        meta: $resource['meta'] ?? NULL,
      );
      $count++;
    }

    $templates = $this->resourceRegistry->getResourceTemplates();
    $seenTemplates = [];
    foreach ($templates as $template) {
      if (empty($template['uriTemplate']) || empty($template['handler'])) {
        continue;
      }

      $uriTemplate = (string) $template['uriTemplate'];
      if (isset($seenTemplates[$uriTemplate])) {
        $this->logger->warning('Skipping duplicate MCP resource template URI: @uri', ['@uri' => $uriTemplate]);
        continue;
      }
      $seenTemplates[$uriTemplate] = TRUE;

      $annotations = NULL;
      if (!empty($template['annotations']) && is_array($template['annotations'])) {
        $annotations = Annotations::fromArray($template['annotations']);
      }

      $builder->addResourceTemplate(
        handler: $template['handler'],
        uriTemplate: (string) $template['uriTemplate'],
        name: $template['name'] ?? NULL,
        description: $template['description'] ?? NULL,
        mimeType: $template['mimeType'] ?? NULL,
        annotations: $annotations,
        meta: $template['meta'] ?? NULL,
      );
      $count++;
    }

    return $count;
  }

  /**
   * Registers prompts with the server builder.
   */
  private function addPrompts(Builder $builder): int {
    if ($this->promptRegistry === NULL) {
      return 0;
    }
    $count = 0;

    $prompts = $this->promptRegistry->getPrompts();
    $seen = [];
    foreach ($prompts as $prompt) {
      if (empty($prompt['handler'])) {
        continue;
      }

      $name = $prompt['name'] ?? NULL;
      $nameKey = is_string($name) ? $name : '';
      if ($nameKey !== '') {
        if (isset($seen[$nameKey])) {
          $this->logger->warning('Skipping duplicate MCP prompt: @name', ['@name' => $nameKey]);
          continue;
        }
        $seen[$nameKey] = TRUE;
      }

      $builder->addPrompt(
        handler: $prompt['handler'],
        name: $prompt['name'] ?? NULL,
        description: $prompt['description'] ?? NULL,
        icons: $prompt['icons'] ?? NULL,
        meta: $prompt['meta'] ?? NULL,
      );
      $count++;
    }

    return $count;
  }

  /**
   * Tells clients what this server really does.
   *
   * Left alone, the SDK advertises logging, completions, resource
   * subscriptions and list-changed notifications. None of them is
   * implemented here, and a client that believes the claim waits for
   * messages that never come.
   */
  private function declareCapabilities(Builder $builder, bool $hasResources, bool $hasPrompts): void {
    if (!method_exists($builder, 'setCapabilities') || !class_exists(ServerCapabilities::class)) {
      return;
    }
    $builder->setCapabilities(new ServerCapabilities(
      tools: TRUE,
      toolsListChanged: FALSE,
      resources: $hasResources,
      resourcesSubscribe: FALSE,
      resourcesListChanged: FALSE,
      prompts: $hasPrompts,
      promptsListChanged: FALSE,
      logging: FALSE,
      completions: FALSE,
    ));
  }

  /**
   * Says how long clients may keep the lists (2026-07-28 revision).
   *
   * That revision puts a lifetime on every list and read result. Without a
   * policy the SDK answers "stale at once", so a client asks for the tool
   * list again before every use. The lists here only change when modules or
   * settings change: five minutes is safe. They are for the asking client
   * alone, never for a shared cache. Resource contents are live site data
   * and stay uncached.
   */
  private function declareCachePolicy(Builder $builder): void {
    if (!method_exists($builder, 'setCachePolicy') || !class_exists(CachePolicy::class)) {
      return;
    }
    $policy = CachePolicy::none();
    foreach (['tools/list', 'prompts/list', 'resources/list', 'resources/templates/list'] as $method) {
      $policy = $policy->withMethod($method, self::LIST_TTL_MS);
    }
    $builder->setCachePolicy($policy);
  }

  /**
   * The server's note to the client's model on how to use it.
   *
   * Clients may put this in the system prompt, and some load only this and
   * the tool names at first, so it stays short and says how to find things.
   */
  public static function instructions(bool $gatewayMode): string {
    $text = 'Tools for administering this Drupal site: content, structure (content types, fields, taxonomy, roles), menus, views, media, blocks, users, config, cache and site health. Which tools exist depends on the modules the site enabled. '
      . 'Tools that only read are safe to call. Tools that write change the live site and need the write scope; if a call is denied, tell the user which scope or permission is missing. Deleting asks for an explicit confirm flag: read the refusal, it says what would be removed. '
      . 'Look up IDs and machine names with a list, get or search tool before you write. When a call fails, the error names the parameter and the problem: fix that and retry.';
    if ($gatewayMode) {
      $text .= ' This server runs in gateway mode: find a tool with ' . ToolApiGateway::DISCOVER_TOOL
        . ' (pass a query), read its inputs with ' . ToolApiGateway::GET_INFO_TOOL
        . ', then run it with ' . ToolApiGateway::EXECUTE_TOOL . '.';
    }
    return $text;
  }

  /**
   * Converts a Tool API plugin ID into an MCP-safe tool name.
   */
  public static function pluginIdToMcpName(string $pluginId): string {
    return str_replace(':', '___', $pluginId);
  }

}
