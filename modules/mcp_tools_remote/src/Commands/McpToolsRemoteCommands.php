<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\mcp_tools_remote\Service\ApiKeyManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush utilities for managing MCP Tools remote API keys.
 */
final class McpToolsRemoteCommands extends DrushCommands {

  public function __construct(
    private readonly ApiKeyManager $apiKeyManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
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

  /**
   * Creates or updates a dedicated execution user + role for the remote endpoint.
   */
  #[CLI\Command(name: 'mcp-tools:remote-setup', aliases: ['mcp-tools-remote:setup'])]
  #[CLI\Usage(name: 'drush mcp-tools:remote-setup', description: 'Create a dedicated remote execution user and configure mcp_tools_remote.settings.uid')]
  #[CLI\Option(name: 'username', description: 'Username for the execution user (default: mcp_tools_remote)')]
  #[CLI\Option(name: 'role', description: 'Role machine name to create/assign (default: mcp_tools_remote_executor)')]
  #[CLI\Option(name: 'categories', description: 'Comma-separated MCP Tools categories to grant (default: site_health,content,config,structure,views,blocks,menus,users,media)')]
  #[CLI\Option(name: 'allow-uid1', description: 'Allow using uid 1 as the execution user (not recommended)')]
  public function setupRemote(array $options = ['username' => 'mcp_tools_remote', 'role' => 'mcp_tools_remote_executor', 'categories' => 'site_health,content,config,structure,views,blocks,menus,users,media', 'allow-uid1' => FALSE]): void {
    $username = trim((string) ($options['username'] ?? 'mcp_tools_remote'));
    if ($username === '') {
      $username = 'mcp_tools_remote';
    }

    $roleId = trim((string) ($options['role'] ?? 'mcp_tools_remote_executor'));
    if ($roleId === '') {
      $roleId = 'mcp_tools_remote_executor';
    }

    $categories = array_filter(array_map('trim', explode(',', (string) ($options['categories'] ?? ''))));
    if (empty($categories)) {
      $categories = ['site_health', 'content', 'config', 'structure', 'views', 'blocks', 'menus', 'users', 'media'];
    }

    $allowUid1 = (bool) ($options['allow-uid1'] ?? $options['allow_uid1'] ?? FALSE);

    // Ensure role exists and has MCP Tools category permissions.
    $role = $this->entityTypeManager->getStorage('user_role')->load($roleId);
    if (!$role) {
      $role = Role::create([
        'id' => $roleId,
        'label' => 'MCP Tools Remote Executor',
      ]);
    }

    $permissions = [];
    foreach ($categories as $category) {
      if ($category === '') {
        continue;
      }
      $permissions[] = 'mcp_tools use ' . $category;
    }
    // Always allow discovery so clients can list categories/tools meaningfully.
    $permissions[] = 'mcp_tools use discovery';
    $permissions = array_values(array_unique($permissions));

    foreach ($permissions as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();

    // Create or load the user.
    $existing = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $username]);
    /** @var \Drupal\user\Entity\User|null $user */
    $user = $existing ? reset($existing) : NULL;

    if (!$user) {
      $user = User::create([
        'name' => $username,
        'mail' => $username . '@example.invalid',
        'status' => 1,
      ]);
      $user->save();
    }

    $uid = (int) $user->id();
    if ($uid === 1 && !$allowUid1) {
      $this->io()->error('Refusing to use uid 1 for remote execution. Re-run with --allow-uid1 to override.');
      return;
    }

    $user->addRole($roleId);
    $user->save();

    // Set remote execution UID in config.
    $this->configFactory->getEditable('mcp_tools_remote.settings')
      ->set('uid', $uid)
      ->set('allow_uid1', $uid === 1 ? $allowUid1 : FALSE)
      ->save();

    $this->io()->success('Remote execution user configured.');
    $this->io()->writeln('Execution user: ' . $username . ' (uid: ' . $uid . ')');
    $this->io()->writeln('Role: ' . $roleId);
    $this->io()->writeln('Granted categories: ' . implode(', ', $categories));
    $this->io()->writeln('');
    $this->io()->writeln('Next steps:');
    $this->io()->writeln('  1) Configure IP allowlist at /admin/config/services/mcp-tools/remote');
    $this->io()->writeln('  2) Create an API key: drush mcp-tools:remote-key-create --label="Remote" --scopes=read --ttl=86400');
    $this->io()->writeln('  3) Enable the endpoint in the UI (or config) and keep keys read-only unless necessary');
  }

}
