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
   * @param bool $growTools
   *   In gateway mode: the list starts with the gateway's tools and the core
   *   ones, and a tool that discover or get info found joins it
   *   (GrowingToolList).
   * @param string[] $coreTools
   *   Plugin ids of the core tools; empty for CORE_TOOLS.
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
    bool $growTools = FALSE,
    array $coreTools = [],
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

    $growTools = $gatewayMode && $growTools && Sdk::registryClass() !== NULL && method_exists($builder, 'setRegistry');
    $registry = NULL;
    if ($growTools) {
      $registryClass = Sdk::registryClass();
      $registry = new $registryClass($this->eventDispatcher, new MinimumLevelLogger($this->logger));
      $builder->setRegistry($registry);
    }

    $resourceCount = $enableResources ? $this->addResources($builder) : 0;
    $promptCount = $enablePrompts ? $this->addPrompts($builder) : 0;
    $this->declareCapabilities($builder, $resourceCount > 0, $promptCount > 0, $growTools);
    [$listed, $hidden] = $this->listedTools($includeAllTools);
    if (method_exists($builder, 'setInstructions')) {
      $builder->setInstructions(self::instructions($gatewayMode, $hidden, $growTools));
    }
    $this->declareCachePolicy($builder, $growTools);

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

      if ($growTools) {
        // Every tool is in the registry; each session is shown the gateway's,
        // the core ones and those it found.
        $this->addListedTools($builder, $listed);
        $core = array_values(array_intersect($coreTools ?: self::CORE_TOOLS, array_keys($listed)));
        $builder->addRequestHandler(new GrowingFindHandler($gateway));
        $builder->addRequestHandler(new GrowingToolList($registry, array_merge(
          [ToolApiGateway::DISCOVER_TOOL, ToolApiGateway::GET_INFO_TOOL, ToolApiGateway::EXECUTE_TOOL],
          array_map([self::class, 'pluginIdToMcpName'], $core),
        )));
      }

      // A model that has found a tool often calls it by its own name.
      // That call goes through the same door as the run tool's.
      $builder->addRequestHandler(new GatewayDirectCallHandler(
        new ToolApiCallToolHandler(
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
        ),
        [ToolApiGateway::DISCOVER_TOOL, ToolApiGateway::GET_INFO_TOOL, ToolApiGateway::EXECUTE_TOOL],
      ));

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

    // The core tools, loaded up front by a client that defers the rest.
    $this->addListedTools($builder, $listed, array_values(array_intersect($coreTools ?: self::CORE_TOOLS, array_keys($listed))));

    return $builder->build();
  }

  /**
   * The core tools a growing list starts with, besides the gateway's.
   *
   * The five most called in our 406 runs, after the gateway's own: search,
   * the content types, the site status, and making and changing content.
   */
  public const CORE_TOOLS = [
    'mcp_tools_search_content',
    'mcp_tools_list_content_types',
    'mcp_tools_get_site_status',
    'mcp_create_content',
    'mcp_update_content',
  ];

  /**
   * Registers the tools, each with its schemas and hints.
   *
   * @param \Mcp\Server\Builder $builder
   *   The server builder.
   * @param \Drupal\tool\Tool\ToolDefinition[] $listed
   *   The tools, by plugin id.
   * @param string[] $alwaysLoad
   *   Plugin ids a client that loads tools on demand should load up front
   *   (_meta "anthropic/alwaysLoad"; Claude Code defers MCP tools by
   *   default and reads it). Other clients ignore it.
   */
  private function addListedTools(Builder $builder, array $listed, array $alwaysLoad = []): void {
    foreach ($listed as $pluginId => $definition) {
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
          meta: in_array((string) $pluginId, $alwaysLoad, TRUE) ? ['anthropic/alwaysLoad' => TRUE] : NULL,
        );
      }
      else {
        $builder->addTool(
          handler: static fn() => NULL,
          name: self::pluginIdToMcpName((string) $pluginId),
          description: (string) $definition->getDescription(),
          annotations: $annotations,
          inputSchema: $this->schemaConverter->toolDefinitionToInputSchema($definition, (string) $pluginId),
          meta: in_array((string) $pluginId, $alwaysLoad, TRUE) ? ['anthropic/alwaysLoad' => TRUE] : NULL,
        );
      }
    }
  }

  /**
   * The tools this session is shown, and how many it is not shown.
   *
   * The scopes and the user are set before a server is built, so the list
   * can be what the session may call. A tool that is left out is still
   * refused by name with "Access denied". A STDIO server builds its list
   * once: a mode switched on or off later changes what runs at once, and the
   * list at the next start.
   *
   * @param bool $includeAllTools
   *   Whether tools of other providers are served too.
   *
   * @return array{0: array<string, \Drupal\tool\Tool\ToolDefinition>, 1: int}
   *   The listed tool definitions by plugin ID, and the number left out
   *   because this session may not call them.
   */
  public function listedTools(bool $includeAllTools): array {
    $listed = [];
    $hidden = 0;
    foreach ($this->toolManager->getDefinitions() as $pluginId => $definition) {
      if (!$definition instanceof ToolDefinition) {
        continue;
      }
      $provider = $definition->getProvider() ?? '';
      if (!$includeAllTools && (!is_string($provider) || !str_starts_with($provider, 'mcp_tools'))) {
        continue;
      }
      if ($this->accessManager !== NULL && !$this->accessManager->listsTool($definition)) {
        $hidden++;
        continue;
      }
      $listed[(string) $pluginId] = $definition;
    }
    return [$listed, $hidden];
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
  private function declareCapabilities(Builder $builder, bool $hasResources, bool $hasPrompts, bool $toolsGrow = FALSE): void {
    if (!method_exists($builder, 'setCapabilities') || !class_exists(ServerCapabilities::class)) {
      return;
    }
    $builder->setCapabilities(new ServerCapabilities(
      tools: TRUE,
      // Only a growing list changes, and then the client is told.
      toolsListChanged: $toolsGrow,
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
  private function declareCachePolicy(Builder $builder, bool $toolsGrow = FALSE): void {
    if (!method_exists($builder, 'setCachePolicy') || !class_exists(CachePolicy::class)) {
      return;
    }
    $policy = CachePolicy::none();
    // A growing tool list is not kept: it changes as the session finds tools.
    $lists = ['prompts/list', 'resources/list', 'resources/templates/list'];
    if (!$toolsGrow) {
      $lists[] = 'tools/list';
    }
    foreach ($lists as $method) {
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
  public static function instructions(bool $gatewayMode, int $hidden = 0, bool $toolsGrow = FALSE): string {
    $text = 'Tools for administering this Drupal site, among them content, content types, fields, taxonomy, roles, menus, views, media, blocks, users, config, cache and site status (Drupal and PHP version, cron), and more from the modules the site enabled, such as redirects, webforms, imports and site templates. Before you tell the user there is no tool for something, look for one. '
      . 'Tools that only read are safe to call. Tools that write change the live site and need the write scope; if a call is denied, tell the user which scope or permission is missing. A few tools ask for a confirm flag before they delete: pass one only when the tool has such an input, and read a refusal, it says what would be removed. '
      . 'Look up IDs and machine names with a list, get or search tool before you write. When a call fails, the error names the parameter and the problem: fix that and retry.';
    if ($gatewayMode) {
      $text .= ' This server runs in gateway mode: find a tool with ' . ToolApiGateway::DISCOVER_TOOL
        . ' (pass a query), read its inputs with ' . ToolApiGateway::GET_INFO_TOOL
        . ', then run it with ' . ToolApiGateway::EXECUTE_TOOL . '.';
      if ($toolsGrow) {
        $text .= ' A tool you read with ' . ToolApiGateway::GET_INFO_TOOL . ' joins your tool list: from then on call it by its name.';
      }
    }
    if ($hidden > 0) {
      $text .= ' ' . $hidden . ' of this site\'s tools are not listed, because this connection may not call them: they need a scope it does not have or a permission its user lacks, or the site is in read-only mode. If the user asks for something that seems to be missing, say that.';
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
