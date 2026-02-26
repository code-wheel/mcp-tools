<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_mcp_server\Commands;

use Drupal\mcp_server\Entity\McpToolConfig;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolOperation;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Helpers for integrating MCP Tools with drupal/mcp_server.
 */
final class McpToolsMcpServerCommands extends DrushCommands {

  private const MCP_SERVER_TOOL_PLUGIN_ID = 'tool_api';

  public function __construct(
    private readonly PluginManagerInterface $toolManager,
    private readonly PluginManagerInterface $mcpServerToolManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Sync MCP Tools Tool API plugins into mcp_server tool configs.
   */
  #[CLI\Command(name: 'mcp-tools:mcp-server-sync', aliases: ['mcp-tools:mcp-server:sync'])]
  #[CLI\Usage(name: 'drush mcp-tools:mcp-server-sync', description: 'Create/update MCP Server tool configs for MCP Tools')]
  #[CLI\Option(name: 'enable', description: 'Enable all synced tool configs')]
  #[CLI\Option(name: 'enable-read', description: 'Enable only read tools (does not disable others)')]
  #[CLI\Option(name: 'auth-mode', description: 'Authentication mode for newly created configs: required|optional|disabled (default: required)')]
  #[CLI\Option(name: 'update-existing', description: 'Update tool_id and label for existing configs (does not change scopes)')]
  #[CLI\Option(name: 'dry-run', description: 'Show what would change without writing config')]
  public function sync(
    array $options = [
      'enable' => FALSE,
      'enable-read' => FALSE,
      'auth-mode' => 'required',
      'update-existing' => FALSE,
      'dry-run' => FALSE,
    ],
  ): void {
    if (!$this->mcpServerToolManager->hasDefinition(self::MCP_SERVER_TOOL_PLUGIN_ID)) {
      $this->io()->error('mcp_server tool plugin "tool_api" not found; cannot sync.');
      return;
    }

    if (!class_exists(McpToolConfig::class)) {
      $this->io()->error('Missing dependency: drupal/mcp_server.');
      return;
    }

    $authMode = (string) ($options['auth-mode'] ?? 'required');
    if (!in_array($authMode, ['required', 'optional', 'disabled'], TRUE)) {
      $this->io()->error('Invalid --auth-mode. Use: required|optional|disabled.');
      return;
    }

    $enableAll = (bool) ($options['enable'] ?? FALSE);
    $enableRead = (bool) ($options['enable-read'] ?? FALSE);
    $updateExisting = (bool) ($options['update-existing'] ?? FALSE);
    $dryRun = (bool) ($options['dry-run'] ?? FALSE);

    $storage = $this->entityTypeManager->getStorage('mcp_tool_config');

    $definitions = $this->toolManager->getDefinitions();
    $discovered = 0;
    $created = 0;
    $updated = 0;
    $enabled = 0;

    foreach ($definitions as $pluginId => $definition) {
      if (!$definition instanceof ToolDefinition) {
        continue;
      }

      $provider = $definition->getProvider() ?? '';
      if (!is_string($provider) || !str_starts_with($provider, 'mcp_tools')) {
        continue;
      }

      $discovered++;

      $configId = self::toMachineName((string) $pluginId);
      $toolId = self::MCP_SERVER_TOOL_PLUGIN_ID . ':' . str_replace(':', '___', (string) $pluginId);
      $label = (string) $definition->getLabel();

      $shouldEnable = $enableAll
        || ($enableRead && (($definition->getOperation() ?? ToolOperation::Transform) === ToolOperation::Read));

      /** @var \Drupal\mcp_server\Entity\McpToolConfig|null $config */
      $config = $storage->load($configId);

      if ($config) {
        $changed = FALSE;

        if ($updateExisting) {
          if ($config->getToolId() !== $toolId) {
            $config->set('tool_id', $toolId);
            $changed = TRUE;
          }
          if ($config->label() !== $label) {
            $config->set('mcp_tool_name', $label);
            $changed = TRUE;
          }
        }

        if ($shouldEnable && !$config->status()) {
          $config->setStatus(TRUE);
          $enabled++;
          $changed = TRUE;
        }

        if ($changed) {
          if (!$dryRun) {
            $config->save();
          }
          $updated++;
        }

        continue;
      }

      $values = [
        'id' => $configId,
        'mcp_tool_name' => $label,
        'tool_id' => $toolId,
        'description' => NULL,
        'status' => $shouldEnable,
        'authentication_mode' => $authMode,
        'scopes' => [],
      ];

      if (!$dryRun) {
        $storage->create($values)->save();
      }

      $created++;
      if ($shouldEnable) {
        $enabled++;
      }
    }

    $this->io()->title('MCP Server Sync');
    $this->io()->listing([
      "Discovered MCP Tools Tool API plugins: {$discovered}",
      "Created MCP Server tool configs: {$created}",
      "Updated MCP Server tool configs: {$updated}",
      "Enabled tool configs: {$enabled}",
      $dryRun ? 'Dry run: no config was written.' : 'Done.',
    ]);
  }

  /**
   * Converts an arbitrary string to a machine-safe config entity ID.
   */
  private static function toMachineName(string $value): string {
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
    $value = trim($value, '_');
    return $value !== '' ? $value : 'mcp_tool';
  }

}
