<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\mcp_tools\Mcp\Resource\ResourceRegistry;
use Drupal\mcp_tools\Mcp\Prompt\PromptRegistry;
use Drupal\mcp_tools\Mcp\Error\ToolErrorHandlerInterface;
use Drupal\tool\Tool\ToolDefinition;
use Mcp\Schema\Annotations;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\Builder;
use Mcp\Server\Session\SessionStoreInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds an MCP Server instance that exposes Drupal Tool API tools.
 */
final class McpToolsServerFactory {

  public function __construct(
    private readonly PluginManagerInterface $toolManager,
    private readonly ToolApiSchemaConverter $schemaConverter,
    private readonly LoggerInterface $logger,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly ?ResourceRegistry $resourceRegistry = NULL,
    private readonly ?PromptRegistry $promptRegistry = NULL,
    private readonly ?ToolErrorHandlerInterface $toolErrorHandler = NULL,
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
   *   Optional session store to use (required for Streamable HTTP across requests).
   * @param int $sessionTtl
   *   Session TTL in seconds.
   * @param bool $gatewayMode
   *   When TRUE, expose only gateway tools (discover/info/execute).
   * @param bool $enableResources
   *   When TRUE, register MCP resources.
   * @param bool $enablePrompts
   *   When TRUE, register MCP prompts.
   *
   * @return \Mcp\Server
   *   MCP server instance.
   */
  public function create(string $serverName, string $serverVersion, int $paginationLimit = 50, bool $includeAllTools = FALSE, ?SessionStoreInterface $sessionStore = NULL, int $sessionTtl = 3600, bool $gatewayMode = FALSE, bool $enableResources = TRUE, bool $enablePrompts = TRUE): Server {
    $builder = Server::builder()
      ->setServerInfo($serverName, $serverVersion)
      ->setPaginationLimit($paginationLimit)
      ->setLogger($this->logger)
      ->setEventDispatcher($this->eventDispatcher);

    if ($sessionStore) {
      $builder->setSession($sessionStore, ttl: $sessionTtl);
    }

    if ($enableResources) {
      $this->addResources($builder);
    }
    if ($enablePrompts) {
      $this->addPrompts($builder);
    }

    if ($gatewayMode) {
      $gateway = new ToolApiGateway(
        $this->toolManager,
        $this->schemaConverter,
        $this->logger,
        $includeAllTools,
        'mcp_tools',
        $this->toolErrorHandler,
        $this->eventDispatcher,
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

      $builder->addTool(
        handler: static fn() => NULL,
        name: self::pluginIdToMcpName((string) $pluginId),
        description: (string) $definition->getDescription(),
        annotations: $annotations,
        inputSchema: $this->schemaConverter->toolDefinitionToInputSchema($definition, (string) $pluginId),
      );
    }

    return $builder->build();
  }

  /**
   * Registers resources and templates with the server builder.
   */
  private function addResources(Builder $builder): void {
    if ($this->resourceRegistry === NULL) {
      return;
    }

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
    }
  }

  /**
   * Registers prompts with the server builder.
   */
  private function addPrompts(Builder $builder): void {
    if ($this->promptRegistry === NULL) {
      return;
    }

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
    }
  }

  /**
   * Converts a Tool API plugin ID into an MCP-safe tool name.
   */
  public static function pluginIdToMcpName(string $pluginId): string {
    return str_replace(':', '___', $pluginId);
  }

}
