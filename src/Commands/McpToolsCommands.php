<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Commands;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\RateLimiter;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\tool\Tool\ToolDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * MCP Tools Drush commands.
 */
class McpToolsCommands extends DrushCommands {

  public function __construct(
    protected AccessManager $accessManager,
    protected RateLimiter $rateLimiter,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mcp_tools.access_manager'),
      $container->get('mcp_tools.rate_limiter'),
      $container->get('module_handler'),
    );
  }

  /**
   * Show MCP Tools status and configuration.
   */
  #[CLI\Command(name: 'mcp:status', aliases: ['mcp-status'])]
  #[CLI\Usage(name: 'drush mcp:status', description: 'Show MCP Tools configuration status')]
  public function status(): void {
    $this->io()->title('MCP Tools Status');

    // Access Status.
    $this->io()->section('Access Control');
    $this->io()->definitionList(
      ['Read-only mode' => $this->accessManager->isReadOnlyMode() ? 'ENABLED' : 'Disabled'],
      ['Config-only mode' => $this->accessManager->isConfigOnlyMode() ? 'ENABLED' : 'Disabled'],
      ['Current scopes' => implode(', ', $this->accessManager->getCurrentScopes())],
      ['Can read' => $this->accessManager->canRead() ? 'Yes' : 'No'],
      ['Can write' => $this->accessManager->canWrite() ? 'Yes' : 'No'],
      ['Can admin' => $this->accessManager->canAdmin() ? 'Yes' : 'No'],
    );

    // Rate Limiting Status.
    $this->io()->section('Rate Limiting');
    $rateStatus = $this->rateLimiter->getStatus();

    if (!$rateStatus['enabled']) {
      $this->io()->text('Rate limiting is <comment>DISABLED</comment>');
    }
    else {
      $this->io()->text('Rate limiting is <info>ENABLED</info>');
      $this->io()->definitionList(
        ['Writes/minute limit' => $rateStatus['limits']['writes_per_minute']],
        ['Writes/hour limit' => $rateStatus['limits']['writes_per_hour']],
        ['Deletes/hour limit' => $rateStatus['limits']['deletes_per_hour']],
        ['Structure changes/hour limit' => $rateStatus['limits']['structure_changes_per_hour']],
      );
    }

    // Enabled Submodules.
    $this->io()->section('Enabled Submodules');
    $submodules = [
      'mcp_tools_content' => 'Content CRUD (4 tools)',
      'mcp_tools_structure' => 'Structure: content types, fields, roles (12 tools)',
      'mcp_tools_users' => 'User management (5 tools)',
      'mcp_tools_menus' => 'Menu management (5 tools)',
      'mcp_tools_views' => 'Views management (6 tools)',
      'mcp_tools_blocks' => 'Block placement (5 tools)',
      'mcp_tools_media' => 'Media management (6 tools)',
      'mcp_tools_webform' => 'Webform integration (7 tools)',
      'mcp_tools_theme' => 'Theme settings (8 tools)',
      'mcp_tools_layout_builder' => 'Layout Builder (9 tools)',
      'mcp_tools_recipes' => 'Drupal Recipes (6 tools)',
      'mcp_tools_config' => 'Configuration management (5 tools)',
    ];

    $rows = [];
    $enabledCount = 0;
    $toolCount = 22; // Base module tools.

    foreach ($submodules as $module => $description) {
      $enabled = $this->moduleHandler->moduleExists($module);
      if ($enabled) {
        $enabledCount++;
        // Extract tool count from description.
        if (preg_match('/\((\d+) tools?\)/', $description, $matches)) {
          $toolCount += (int) $matches[1];
        }
      }
      $rows[] = [
        $module,
        $enabled ? '<info>✓ Enabled</info>' : '<comment>✗ Disabled</comment>',
        $description,
      ];
    }

    $this->io()->table(['Module', 'Status', 'Description'], $rows);
    $this->io()->text("Enabled: $enabledCount/12 submodules, $toolCount total tools available");

    // Security Warnings.
    $this->io()->section('Security Recommendations');
    $warnings = [];

    if (!$this->accessManager->isReadOnlyMode() && !$this->accessManager->isConfigOnlyMode()) {
      $warnings[] = '<comment>⚠</comment> Read-only mode is disabled. Enable read-only or config-only for production.';
    }

    if (!$rateStatus['enabled']) {
      $warnings[] = '<comment>⚠</comment> Rate limiting is disabled. Enable for production.';
    }

    $scopes = $this->accessManager->getCurrentScopes();
    if (in_array('write', $scopes) || in_array('admin', $scopes)) {
      $warnings[] = '<comment>⚠</comment> Write/admin scopes active. Use read-only for production.';
    }

    if (empty($warnings)) {
      $this->io()->success('All security recommendations met.');
    }
    else {
      foreach ($warnings as $warning) {
        $this->io()->text($warning);
      }
    }
  }

  /**
   * List all available MCP tools.
   */
  #[CLI\Command(name: 'mcp:tools', aliases: ['mcp-tools'])]
  #[CLI\Usage(name: 'drush mcp:tools', description: 'List all available MCP tools')]
  #[CLI\Option(name: 'format', description: 'Output format (table, json)')]
  public function tools(array $options = ['format' => 'table']): void {
    $tools = [];

    // Get all tool plugins.
    if ($this->moduleHandler->moduleExists('tool')) {
      /** @var \Drupal\tool\Tool\ToolManager $toolManager */
      $toolManager = \Drupal::service('plugin.manager.tool');
      $definitions = $toolManager->getDefinitions();

      foreach ($definitions as $id => $definition) {
        if (!$definition instanceof ToolDefinition) {
          continue;
        }

        // Filter to MCP tools only.
        $provider = $definition->getProvider() ?? '';
        if (is_string($provider) && str_starts_with($provider, 'mcp_tools')) {
          $tools[] = [
            'id' => $id,
            'label' => (string) $definition->getLabel(),
            'provider' => $provider,
          ];
        }
      }
    }

    if ($options['format'] === 'json') {
      $this->io()->text(json_encode($tools, JSON_PRETTY_PRINT));
      return;
    }

    $this->io()->title('Available MCP Tools');

    if (empty($tools)) {
      $this->io()->warning('No MCP tools found. Make sure tool module is enabled.');
      return;
    }

    $rows = array_map(fn($t) => [$t['id'], $t['label'], $t['provider']], $tools);
    $this->io()->table(['Tool ID', 'Label', 'Provider'], $rows);
    $this->io()->text('Total: ' . count($tools) . ' tools');
  }

  /**
   * Reset rate limits for the current client.
   */
  #[CLI\Command(name: 'mcp:reset-limits', aliases: ['mcp-reset'])]
  #[CLI\Usage(name: 'drush mcp:reset-limits', description: 'Reset rate limits for current client')]
  public function resetLimits(): void {
    $this->rateLimiter->resetLimits();
    $this->io()->success('Rate limits reset for current client.');
  }

}
