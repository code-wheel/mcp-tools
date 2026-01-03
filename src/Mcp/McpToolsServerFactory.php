<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolManager;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds an MCP Server instance that exposes Drupal Tool API tools.
 */
final class McpToolsServerFactory {

  public function __construct(
    private readonly ToolManager $toolManager,
    private readonly ToolApiSchemaConverter $schemaConverter,
    private readonly LoggerInterface $logger,
    private readonly EventDispatcherInterface $eventDispatcher,
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
   *
   * @return \Mcp\Server
   *   MCP server instance.
   */
  public function create(string $serverName, string $serverVersion, int $paginationLimit = 50, bool $includeAllTools = FALSE): Server {
    $builder = Server::builder()
      ->setServerInfo($serverName, $serverVersion)
      ->setPaginationLimit($paginationLimit)
      ->setLogger($this->logger)
      ->setEventDispatcher($this->eventDispatcher);

    // Intercept tool calls and execute Tool API tools directly.
    $builder->addRequestHandler(new ToolApiCallToolHandler(
      $this->toolManager,
      $this->logger,
      $includeAllTools,
      'mcp_tools',
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

      $annotationsData = $this->schemaConverter->toolDefinitionToAnnotations($definition);
      $annotations = ToolAnnotations::fromArray($annotationsData);

      $builder->addTool(
        handler: static fn() => NULL,
        name: self::pluginIdToMcpName((string) $pluginId),
        description: (string) $definition->getDescription(),
        annotations: $annotations,
        inputSchema: $this->schemaConverter->toolDefinitionToInputSchema($definition),
      );
    }

    return $builder->build();
  }

  /**
   * Converts a Tool API plugin ID into an MCP-safe tool name.
   */
  public static function pluginIdToMcpName(string $pluginId): string {
    return str_replace(':', '___', $pluginId);
  }

}

