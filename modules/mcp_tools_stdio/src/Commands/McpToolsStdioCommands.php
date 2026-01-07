<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_stdio\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\mcp_tools\Mcp\McpToolsServerFactory;
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
  #[CLI\Option(name: 'scope', description: 'Override scopes for this process (comma-separated: read,write,admin)')]
  #[CLI\Option(name: 'uid', description: 'Drupal user ID to run tool execution as (defaults to Drush bootstrap user)')]
  #[CLI\Option(name: 'all-tools', description: 'Expose all Tool API tools (not only mcp_tools providers)')]
  public function serve(array $options = ['scope' => NULL, 'uid' => NULL, 'all-tools' => FALSE]): void {
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

    // Optionally override scopes for this process.
    if (!empty($options['scope']) && is_string($options['scope'])) {
      $scopes = array_filter(array_map('trim', explode(',', $options['scope'])));
      if (!empty($scopes)) {
        $this->accessManager->setScopes($scopes);
      }
    }

    $includeAllTools = (bool) ($options['all-tools'] ?? FALSE);

    $schemaConverter = new ToolApiSchemaConverter();
    $serverFactory = new McpToolsServerFactory(
      $this->toolManager,
      $schemaConverter,
      $this->mcpLogger,
      $this->eventDispatcher,
    );

    fwrite(\STDERR, "MCP Tools STDIO Server\n");
    fwrite(\STDERR, "Starting MCP server over STDIO...\n");

    $server = $serverFactory->create(
      'Drupal MCP Tools',
      '1.0.0',
      50,
      $includeAllTools,
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
