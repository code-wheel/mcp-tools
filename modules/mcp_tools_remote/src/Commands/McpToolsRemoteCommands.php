<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Commands;

use Drupal\mcp_tools_remote\Service\ApiKeyManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush utilities for managing MCP Tools remote API keys.
 */
final class McpToolsRemoteCommands extends DrushCommands {

  public function __construct(
    private readonly ApiKeyManager $apiKeyManager,
  ) {
    parent::__construct();
  }

  /**
   * Creates a new API key for the remote endpoint.
   */
  #[CLI\Command(name: 'mcp-tools:remote-key-create', aliases: ['mcp-tools-remote:key-create'])]
  #[CLI\Usage(name: 'drush mcp-tools:remote-key-create --label=\"My Key\" --scopes=read,write', description: 'Create an API key and print it (shown once)')]
  #[CLI\Option(name: 'label', description: 'Key label for admins')]
  #[CLI\Option(name: 'scopes', description: 'Comma-separated scopes: read,write,admin (default: read)')]
  #[CLI\Option(name: 'ttl', description: 'Optional time-to-live in seconds (0 = no expiry)')]
  public function createKey(array $options = ['label' => '', 'scopes' => 'read', 'ttl' => 0]): void {
    $label = (string) ($options['label'] ?? '');
    $scopes = array_filter(array_map('trim', explode(',', (string) ($options['scopes'] ?? 'read'))));
    if (empty($scopes)) {
      $scopes = ['read'];
    }

    $ttl = (int) ($options['ttl'] ?? 0);
    $ttl = $ttl > 0 ? $ttl : NULL;

    $created = $this->apiKeyManager->createKey($label, $scopes, $ttl);

    $this->io()->writeln('Key ID: ' . $created['key_id']);
    $this->io()->writeln('API Key: ' . $created['api_key']);
    $this->io()->writeln('');
    $this->io()->writeln('Store this API key now; it cannot be shown again.');
  }

  /**
   * Lists existing API keys (redacted).
   */
  #[CLI\Command(name: 'mcp-tools:remote-key-list', aliases: ['mcp-tools-remote:key-list'])]
  #[CLI\Usage(name: 'drush mcp-tools:remote-key-list', description: 'List remote API keys (hashes are not shown)')]
  #[CLI\Option(name: 'format', description: 'Output format (table,json)')]
  public function listKeys(array $options = ['format' => 'table']): void {
    $keys = $this->apiKeyManager->listKeys();

    if (($options['format'] ?? 'table') === 'json') {
      $this->io()->writeln(json_encode($keys, JSON_PRETTY_PRINT));
      return;
    }

    $rows = [];
    foreach ($keys as $id => $data) {
      $rows[] = [
        $id,
        $data['label'] ?? '',
        implode(',', $data['scopes'] ?? []),
        $data['created'] ?? '',
        $data['last_used'] ?? '',
      ];
    }

    $this->io()->table(['ID', 'Label', 'Scopes', 'Created', 'Last used'], $rows);
  }

  /**
   * Revokes (deletes) an API key by ID.
   */
  #[CLI\Command(name: 'mcp-tools:remote-key-revoke', aliases: ['mcp-tools-remote:key-revoke'])]
  #[CLI\Usage(name: 'drush mcp-tools:remote-key-revoke 0123abcd', description: 'Revoke a remote API key')]
  public function revokeKey(string $keyId): void {
    if ($this->apiKeyManager->revokeKey($keyId)) {
      $this->io()->success('Revoked key: ' . $keyId);
      return;
    }

    $this->io()->warning('Key not found: ' . $keyId);
  }

}
