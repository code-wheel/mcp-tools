<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_stdio\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\mcp_tools\Mcp\McpToolsServerFactory;
use Drupal\mcp_tools\Mcp\Error\ToolErrorHandlerInterface;
use Drupal\mcp_tools\Mcp\Resource\ResourceRegistry;
use Drupal\mcp_tools\Mcp\Prompt\PromptRegistry;
use Drupal\mcp_tools\Mcp\ServerConfigRepository;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\tool\Tool\ToolManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Mcp\Server\Transport\StdioTransport;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Drush command to run MCP Tools over STDIO.
 */
final class McpToolsStdioCommands extends DrushCommands {

  public function __construct(
    private readonly ToolManager $toolManager,
    private readonly ResourceRegistry $resourceRegistry,
    private readonly PromptRegistry $promptRegistry,
    private readonly ServerConfigRepository $serverConfigRepository,
    private readonly ToolErrorHandlerInterface $toolErrorHandler,
    private readonly AccessManager $accessManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountSwitcherInterface $accountSwitcher,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly LoggerInterface $mcpLogger,
  ) {
    parent::__construct();
  }

  /**
   * Start an MCP server over STDIO (for local MCP clients).
   */
  #[CLI\Command(name: 'mcp-tools:serve', aliases: ['mcp-tools-server', 'mcp-tools:server'])]
  #[CLI\Usage(name: 'drush mcp-tools:serve', description: 'Start an MCP server over STDIO exposing Tool API tools')]
  #[CLI\Option(name: 'server', description: 'Server profile ID from mcp_tools_servers.settings')]
  #[CLI\Option(name: 'scope', description: 'Override scopes for this process (comma-separated: read,write,admin)')]
  #[CLI\Option(name: 'uid', description: 'Drupal user ID to run tool execution as (defaults to Drush bootstrap user)')]
  #[CLI\Option(name: 'all-tools', description: 'Expose all Tool API tools (not only mcp_tools providers)')]
  #[CLI\Option(name: 'gateway', description: 'Expose gateway tools (discover/info/execute) instead of the full tool list')]
  public function serve(array $options = ['server' => NULL, 'scope' => NULL, 'uid' => NULL, 'all-tools' => FALSE, 'gateway' => FALSE]): void {
    if (!class_exists(\Mcp\Server::class)) {
      fwrite(\STDERR, "Missing dependency: mcp/sdk. Run: composer require mcp/sdk:^0.2\n");
      return;
    }

    // Optionally switch to a specific Drupal user.
    $switched = FALSE;
    if (!empty($options['uid']) && is_numeric($options['uid'])) {
      $account = $this->entityTypeManager->getStorage('user')->load((int) $options['uid']);
      if ($account) {
        $this->accountSwitcher->switchTo($account);
        $switched = TRUE;
      }
      else {
        fwrite(\STDERR, "User not found for --uid={$options['uid']}; continuing with current user.\n");
      }
    }

    $serverId = isset($options['server']) && is_string($options['server']) ? trim($options['server']) : NULL;
    $serverConfig = $this->serverConfigRepository->getServer($serverId);
    if (!$serverConfig) {
      fwrite(\STDERR, "Unknown MCP server profile" . ($serverId ? ": {$serverId}" : '') . ".\n");
      if ($switched) {
        $this->accountSwitcher->switchBack();
      }
      return;
    }

    if (!$this->serverConfigRepository->allowsTransport($serverConfig, 'stdio')) {
      fwrite(\STDERR, "Server profile does not allow STDIO transport.\n");
      if ($switched) {
        $this->accountSwitcher->switchBack();
      }
      return;
    }

    // Optionally override scopes for this process.
    if (!empty($options['scope']) && is_string($options['scope'])) {
      $scopes = array_filter(array_map('trim', explode(',', $options['scope'])));
      if (!empty($scopes)) {
        $this->accessManager->setScopes($scopes);
      }
    }
    elseif (!empty($serverConfig['scopes'])) {
      $this->accessManager->setScopes((array) $serverConfig['scopes']);
    }

    $includeAllTools = (bool) ($options['all-tools'] ?? ($serverConfig['include_all_tools'] ?? FALSE));
    $gatewayMode = (bool) ($options['gateway'] ?? ($serverConfig['gateway_mode'] ?? FALSE));
    $enableResources = (bool) ($serverConfig['enable_resources'] ?? TRUE);
    $enablePrompts = (bool) ($serverConfig['enable_prompts'] ?? TRUE);

    $access = $this->serverConfigRepository->checkAccess($serverConfig, NULL);
    if (!$access['allowed']) {
      fwrite(\STDERR, ($access['message'] ?? 'Access denied.') . "\n");
      if ($switched) {
        $this->accountSwitcher->switchBack();
      }
      return;
    }

    $schemaConverter = new ToolApiSchemaConverter();
    $serverFactory = new McpToolsServerFactory(
      $this->toolManager,
      $schemaConverter,
      $this->mcpLogger,
      $this->eventDispatcher,
      $this->resourceRegistry,
      $this->promptRegistry,
      $this->toolErrorHandler,
    );

    fwrite(\STDERR, "MCP Tools STDIO Server\n");
    fwrite(\STDERR, "Starting MCP server over STDIO...\n");

    $server = $serverFactory->create(
      (string) ($serverConfig['name'] ?? 'Drupal MCP Tools'),
      (string) ($serverConfig['version'] ?? '1.0.0'),
      (int) ($serverConfig['pagination_limit'] ?? 50),
      $includeAllTools,
      NULL,
      3600,
      $gatewayMode,
      $enableResources,
      $enablePrompts,
    );

    try {
      $server->run(new StdioTransport());
    }
    finally {
      if ($switched) {
        $this->accountSwitcher->switchBack();
      }
    }
  }

}
