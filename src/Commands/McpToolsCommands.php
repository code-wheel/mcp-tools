<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\mcp_tools\Mcp\McpToolsServerFactory;
use Drupal\mcp_tools\Mcp\Prompt\PromptRegistry;
use Drupal\mcp_tools\Mcp\Resource\ResourceRegistry;
use Drupal\mcp_tools\Mcp\ServerConfigRepository;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\ClientConfigGenerator;
use Drupal\mcp_tools\Service\RateLimiter;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * MCP Tools Drush commands.
 */
class McpToolsCommands extends DrushCommands {

  public function __construct(
    protected AccessManager $accessManager,
    protected RateLimiter $rateLimiter,
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigFactoryInterface $configFactory,
    protected ServerConfigRepository $serverConfigRepository,
    protected ResourceRegistry $resourceRegistry,
    protected PromptRegistry $promptRegistry,
    protected ToolManager $toolManager,
    protected ModuleInstallerInterface $moduleInstaller,
    protected ModuleExtensionList $moduleList,
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
      $container->get('config.factory'),
      $container->get('mcp_tools.server_config_repository'),
      $container->get('mcp_tools.resource_registry'),
      $container->get('mcp_tools.prompt_registry'),
      $container->get('plugin.manager.tool'),
      $container->get('module_installer'),
      $container->get('extension.list.module'),
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

    // Enabled Submodules - grouped by category.
    $this->io()->section('Enabled Submodules');

    // Core-only submodules (no contrib dependencies).
    $coreSubmodules = [
      'mcp_tools_content' => 'Content CRUD',
      'mcp_tools_structure' => 'Content types, fields, roles, taxonomy',
      'mcp_tools_users' => 'User management',
      'mcp_tools_menus' => 'Menu management',
      'mcp_tools_views' => 'Views management',
      'mcp_tools_blocks' => 'Block placement',
      'mcp_tools_media' => 'Media management',
      'mcp_tools_theme' => 'Theme settings',
      'mcp_tools_layout_builder' => 'Layout Builder',
      'mcp_tools_recipes' => 'Drupal Recipes',
      'mcp_tools_config' => 'Configuration management',
      'mcp_tools_cache' => 'Cache management',
      'mcp_tools_cron' => 'Cron management',
      'mcp_tools_batch' => 'Batch operations',
      'mcp_tools_templates' => 'Site templates',
      'mcp_tools_migration' => 'Content migration',
      'mcp_tools_analysis' => 'Site analysis',
      'mcp_tools_moderation' => 'Content moderation',
      'mcp_tools_image_styles' => 'Image styles',
      'mcp_tools_jsonapi' => 'Generic entity CRUD via JSON:API',
    ];

    // Contrib-dependent submodules.
    $contribSubmodules = [
      'mcp_tools_webform' => 'Webform (requires webform)',
      'mcp_tools_paragraphs' => 'Paragraphs (requires paragraphs)',
      'mcp_tools_redirect' => 'Redirects (requires redirect)',
      'mcp_tools_pathauto' => 'Path auto (requires pathauto)',
      'mcp_tools_metatag' => 'Metatag (requires metatag)',
      'mcp_tools_scheduler' => 'Scheduler (requires scheduler)',
      'mcp_tools_search_api' => 'Search API (requires search_api)',
      'mcp_tools_sitemap' => 'Sitemap (requires simple_sitemap)',
      'mcp_tools_entity_clone' => 'Entity clone (requires entity_clone)',
      'mcp_tools_ultimate_cron' => 'Ultimate Cron (requires ultimate_cron)',
    ];

    // Infrastructure submodules.
    $infraSubmodules = [
      'mcp_tools_stdio' => 'STDIO transport',
      'mcp_tools_remote' => 'HTTP transport',
      'mcp_tools_observability' => 'Event logging',
      'mcp_tools_mcp_server' => 'MCP Server bridge (requires mcp_server)',
    ];

    $rows = [];
    $enabledCore = 0;
    $enabledContrib = 0;
    $enabledInfra = 0;

    $this->io()->text('<info>Core-only submodules:</info>');
    foreach ($coreSubmodules as $module => $description) {
      $enabled = $this->moduleHandler->moduleExists($module);
      if ($enabled) {
        $enabledCore++;
      }
      $rows[] = [$module, $enabled ? '<info>✓</info>' : '<comment>✗</comment>', $description];
    }
    $this->io()->table(['Module', '', 'Description'], $rows);

    $rows = [];
    $this->io()->text('<info>Contrib-dependent submodules:</info>');
    foreach ($contribSubmodules as $module => $description) {
      $enabled = $this->moduleHandler->moduleExists($module);
      if ($enabled) {
        $enabledContrib++;
      }
      $rows[] = [$module, $enabled ? '<info>✓</info>' : '<comment>✗</comment>', $description];
    }
    $this->io()->table(['Module', '', 'Description'], $rows);

    $rows = [];
    $this->io()->text('<info>Infrastructure submodules:</info>');
    foreach ($infraSubmodules as $module => $description) {
      $enabled = $this->moduleHandler->moduleExists($module);
      if ($enabled) {
        $enabledInfra++;
      }
      $rows[] = [$module, $enabled ? '<info>✓</info>' : '<comment>✗</comment>', $description];
    }
    $this->io()->table(['Module', '', 'Description'], $rows);

    // Get actual tool count from tool manager.
    $toolCount = $this->countMcpTools();
    $totalSubmodules = count($coreSubmodules) + count($contribSubmodules) + count($infraSubmodules);
    $enabledCount = $enabledCore + $enabledContrib + $enabledInfra;

    $this->io()->text("Enabled: $enabledCount/$totalSubmodules submodules, $toolCount MCP tools available");

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
      $definitions = $this->toolManager->getDefinitions();

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
   * List configured MCP server profiles.
   */
  #[CLI\Command(name: 'mcp:servers', aliases: ['mcp-servers'])]
  #[CLI\Usage(name: 'drush mcp:servers', description: 'List configured MCP server profiles')]
  #[CLI\Option(name: 'format', description: 'Output format (table, json)')]
  public function servers(array $options = ['format' => 'table']): void {
    $servers = $this->serverConfigRepository->getServers();

    if (empty($servers)) {
      $this->io()->warning('No MCP server profiles found.');
      return;
    }

    $defaultId = $this->serverConfigRepository->getDefaultServerId($servers);

    if ($options['format'] === 'json') {
      $this->io()->text(json_encode([
        'default_server' => $defaultId,
        'servers' => $servers,
      ], JSON_PRETTY_PRINT));
      return;
    }

    $rows = [];
    foreach ($servers as $id => $server) {
      $rows[] = [
        $id,
        $server['name'] ?? 'Unknown',
        $id === $defaultId ? 'yes' : '',
        !empty($server['scopes']) ? implode(', ', (array) $server['scopes']) : '-',
        !empty($server['gateway_mode']) ? 'gateway' : 'full',
        !empty($server['include_all_tools']) ? 'all' : 'mcp_tools',
        !empty($server['enable_resources']) ? 'yes' : 'no',
        !empty($server['enable_prompts']) ? 'yes' : 'no',
      ];
    }

    $this->io()->title('MCP Server Profiles');
    $this->io()->table(['ID', 'Name', 'Default', 'Scopes', 'Mode', 'Tools', 'Resources', 'Prompts'], $rows);
  }

  /**
   * Show details for a single MCP server profile.
   */
  #[CLI\Command(name: 'mcp:server-info', aliases: ['mcp-server'])]
  #[CLI\Usage(name: 'drush mcp:server-info --server=default', description: 'Show details for a server profile')]
  #[CLI\Option(name: 'server', description: 'Server profile ID')]
  #[CLI\Option(name: 'format', description: 'Output format (table, json)')]
  #[CLI\Option(name: 'tools', description: 'List tool names for this server')]
  #[CLI\Option(name: 'resources', description: 'List resources for this server')]
  #[CLI\Option(name: 'prompts', description: 'List prompts for this server')]
  public function serverInfo(array $options = ['server' => NULL, 'format' => 'table', 'tools' => FALSE, 'resources' => FALSE, 'prompts' => FALSE]): void {
    $serverId = isset($options['server']) && is_string($options['server']) ? trim($options['server']) : NULL;
    $server = $this->serverConfigRepository->getServer($serverId);

    if (!$server) {
      $this->io()->error('Unknown MCP server profile' . ($serverId ? ": {$serverId}" : '') . '.');
      return;
    }

    $allowedTools = $this->getAllowedTools((bool) ($server['include_all_tools'] ?? FALSE));
    $toolNames = array_map(
      static fn(string $id): string => McpToolsServerFactory::pluginIdToMcpName($id),
      array_keys($allowedTools),
    );
    sort($toolNames);

    $resourceCount = 0;
    $resourceTemplates = 0;
    if (!empty($server['enable_resources'])) {
      $resources = $this->resourceRegistry->getResources();
      $templates = $this->resourceRegistry->getResourceTemplates();
      $resourceCount = count($this->dedupeListByKey($resources, 'uri'));
      $resourceTemplates = count($this->dedupeListByKey($templates, 'uriTemplate'));
    }

    $promptCount = 0;
    if (!empty($server['enable_prompts'])) {
      $prompts = $this->promptRegistry->getPrompts();
      $promptCount = count($this->dedupeListByKey($prompts, 'name'));
    }

    $availableToolCount = count($allowedTools);
    $exposedToolCount = !empty($server['gateway_mode']) ? 3 : $availableToolCount;

    if ($options['format'] === 'json') {
      $payload = [
        'id' => $server['id'] ?? $serverId,
        'name' => $server['name'] ?? 'Drupal MCP Tools',
        'version' => $server['version'] ?? '1.0.0',
        'pagination_limit' => $server['pagination_limit'] ?? 50,
        'scopes' => $server['scopes'] ?? [],
        'gateway_mode' => (bool) ($server['gateway_mode'] ?? FALSE),
        'include_all_tools' => (bool) ($server['include_all_tools'] ?? FALSE),
        'enable_resources' => (bool) ($server['enable_resources'] ?? TRUE),
        'enable_prompts' => (bool) ($server['enable_prompts'] ?? TRUE),
        'transports' => $server['transports'] ?? [],
        'counts' => [
          'available_tools' => $availableToolCount,
          'exposed_tools' => $exposedToolCount,
          'resources' => $resourceCount,
          'resource_templates' => $resourceTemplates,
          'prompts' => $promptCount,
        ],
      ];

      if (!empty($options['tools'])) {
        $payload['tools'] = $toolNames;
      }
      if (!empty($options['resources'])) {
        $payload['resources'] = !empty($server['enable_resources'])
          ? array_values(array_column($this->resourceRegistry->getResources(), 'uri'))
          : [];
        $payload['resource_templates'] = !empty($server['enable_resources'])
          ? array_values(array_column($this->resourceRegistry->getResourceTemplates(), 'uriTemplate'))
          : [];
      }
      if (!empty($options['prompts'])) {
        $payload['prompts'] = !empty($server['enable_prompts'])
          ? array_values(array_column($this->promptRegistry->getPrompts(), 'name'))
          : [];
      }

      $this->io()->text(json_encode($payload, JSON_PRETTY_PRINT));
      return;
    }

    $this->io()->title('MCP Server Profile: ' . ($server['id'] ?? $serverId));
    $this->io()->definitionList(
      ['Name' => $server['name'] ?? 'Drupal MCP Tools'],
      ['Version' => $server['version'] ?? '1.0.0'],
      ['Scopes' => !empty($server['scopes']) ? implode(', ', (array) $server['scopes']) : '-'],
      ['Gateway mode' => !empty($server['gateway_mode']) ? 'Enabled' : 'Disabled'],
      ['Include all tools' => !empty($server['include_all_tools']) ? 'Yes' : 'No'],
      ['Resources enabled' => !empty($server['enable_resources']) ? 'Yes' : 'No'],
      ['Prompts enabled' => !empty($server['enable_prompts']) ? 'Yes' : 'No'],
      ['Transports' => !empty($server['transports']) ? implode(', ', (array) $server['transports']) : 'all'],
      ['Pagination limit' => (string) ($server['pagination_limit'] ?? 50)],
    );

    $this->io()->section('Component Counts');
    $this->io()->definitionList(
      ['Available tools' => (string) $availableToolCount],
      ['Exposed tools' => (string) $exposedToolCount],
      ['Resources' => (string) $resourceCount],
      ['Resource templates' => (string) $resourceTemplates],
      ['Prompts' => (string) $promptCount],
    );

    if (!empty($options['tools'])) {
      $this->io()->section('Tools');
      $this->io()->listing($toolNames ?: ['(none)']);
    }

    if (!empty($options['resources'])) {
      $resources = !empty($server['enable_resources'])
        ? array_values(array_column($this->dedupeListByKey($this->resourceRegistry->getResources(), 'uri'), 'uri'))
        : [];
      $templates = !empty($server['enable_resources'])
        ? array_values(array_column($this->dedupeListByKey($this->resourceRegistry->getResourceTemplates(), 'uriTemplate'), 'uriTemplate'))
        : [];
      $this->io()->section('Resources');
      $this->io()->listing($resources ?: ['(none)']);
      $this->io()->section('Resource Templates');
      $this->io()->listing($templates ?: ['(none)']);
    }

    if (!empty($options['prompts'])) {
      $prompts = !empty($server['enable_prompts'])
        ? array_values(array_column($this->dedupeListByKey($this->promptRegistry->getPrompts(), 'name'), 'name'))
        : [];
      $this->io()->section('Prompts');
      $this->io()->listing($prompts ?: ['(none)']);
    }
  }

  /**
   * Smoke-test server configuration and dependencies.
   */
  #[CLI\Command(name: 'mcp:server-smoke', aliases: ['mcp-smoke'])]
  #[CLI\Usage(name: 'drush mcp:server-smoke --server=default', description: 'Smoke-test server config and dependencies')]
  #[CLI\Option(name: 'server', description: 'Server profile ID')]
  public function serverSmoke(array $options = ['server' => NULL]): void {
    $serverId = isset($options['server']) && is_string($options['server']) ? trim($options['server']) : NULL;
    $server = $this->serverConfigRepository->getServer($serverId);

    if (!$server) {
      $this->io()->error('Unknown MCP server profile' . ($serverId ? ": {$serverId}" : '') . '.');
      return;
    }

    $issues = [];

    if (!class_exists(\Mcp\Server::class)) {
      $issues[] = 'Missing dependency: mcp/sdk (composer require mcp/sdk:^0.2).';
    }

    if (!$this->moduleHandler->moduleExists('tool')) {
      $issues[] = 'Drupal Tool API module is not enabled.';
    }

    $access = $this->serverConfigRepository->checkAccess($server, NULL);
    if (!$access['allowed']) {
      $issues[] = $access['message'] ?? 'Access denied by server permission callback.';
    }

    $allowedTools = $this->getAllowedTools((bool) ($server['include_all_tools'] ?? FALSE));
    if (empty($allowedTools)) {
      $issues[] = 'No Tool API tools are available for this server profile.';
    }

    if (!empty($server['enable_resources'])) {
      $resources = $this->resourceRegistry->getResources();
      $templates = $this->resourceRegistry->getResourceTemplates();
      $resourceCount = count($this->dedupeListByKey($resources, 'uri'));
      $resourceTemplates = count($this->dedupeListByKey($templates, 'uriTemplate'));
      if ($resourceCount === 0 && $resourceTemplates === 0) {
        $issues[] = 'Resources are enabled but none are registered.';
      }
    }

    if (!empty($server['enable_prompts'])) {
      $prompts = $this->promptRegistry->getPrompts();
      if (count($this->dedupeListByKey($prompts, 'name')) === 0) {
        $issues[] = 'Prompts are enabled but none are registered.';
      }
    }

    if (!empty($issues)) {
      $this->io()->warning('Smoke test detected issues:');
      $this->io()->listing($issues);
      return;
    }

    $this->io()->success('Smoke test passed.');
  }

  /**
   * Scaffold a simple MCP component module.
   */
  #[CLI\Command(name: 'mcp:scaffold', aliases: ['mcp-scaffold'])]
  #[CLI\Usage(name: 'drush mcp:scaffold --machine-name=my_module', description: 'Scaffold a module with MCP component registration')]
  #[CLI\Option(name: 'machine-name', description: 'Module machine name (e.g. my_module)')]
  #[CLI\Option(name: 'name', description: 'Human-readable module name')]
  #[CLI\Option(name: 'description', description: 'Module description')]
  #[CLI\Option(name: 'destination', description: 'Destination directory (defaults to DRUPAL_ROOT/modules/custom)')]
  #[CLI\Option(name: 'force', description: 'Overwrite existing files if the module directory exists')]
  public function scaffold(array $options = [
    'machine-name' => NULL,
    'name' => NULL,
    'description' => NULL,
    'destination' => NULL,
    'force' => FALSE,
  ]): void {
    $machineName = isset($options['machine-name']) && is_string($options['machine-name'])
      ? trim($options['machine-name'])
      : '';

    if ($machineName === '') {
      $this->io()->error('Missing --machine-name (e.g. my_module).');
      return;
    }

    if (!preg_match('/^[a-z][a-z0-9_]*$/', $machineName)) {
      $this->io()->error('Invalid machine name. Use lowercase letters, numbers, and underscores.');
      return;
    }

    $humanName = isset($options['name']) && is_string($options['name']) && $options['name'] !== ''
      ? trim($options['name'])
      : ucwords(str_replace('_', ' ', $machineName));

    $description = isset($options['description']) && is_string($options['description']) && $options['description'] !== ''
      ? trim($options['description'])
      : 'MCP Tools components for ' . $humanName . '.';

    $destination = isset($options['destination']) && is_string($options['destination']) && $options['destination'] !== ''
      ? rtrim($options['destination'], DIRECTORY_SEPARATOR)
      : rtrim(\Drupal::root() . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'custom', DIRECTORY_SEPARATOR);

    $modulePath = $destination . DIRECTORY_SEPARATOR . $machineName;
    $force = (bool) ($options['force'] ?? FALSE);

    if (is_dir($modulePath) && !$force) {
      $this->io()->error('Destination already exists. Re-run with --force to overwrite files.');
      return;
    }

    if (!is_dir($destination) && !mkdir($destination, 0775, TRUE) && !is_dir($destination)) {
      $this->io()->error('Failed to create destination directory: ' . $destination);
      return;
    }

    $templateRoot = $this->getScaffoldTemplateRoot();
    if ($templateRoot === NULL) {
      $this->io()->error('Scaffold templates not found.');
      return;
    }

    $replacements = [
      '{{ machine_name }}' => $machineName,
      '{{ name }}' => $humanName,
      '{{ description }}' => $description,
    ];

    $created = $this->renderScaffoldTemplates($templateRoot, $modulePath, $replacements, $force, $machineName);
    if (empty($created)) {
      $this->io()->warning('No files were generated.');
      return;
    }

    $this->io()->success('Scaffolded module: ' . $modulePath);
    $this->io()->listing($created);
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

  /**
   * Apply development profile: enable core submodules and set development mode.
   */
  #[CLI\Command(name: 'mcp:dev-profile', aliases: ['mcp-dev'])]
  #[CLI\Usage(name: 'drush mcp:dev-profile', description: 'Apply development preset and enable recommended submodules')]
  #[CLI\Option(name: 'skip-modules', description: 'Skip enabling submodules (only apply config preset)')]
  public function devProfile(array $options = ['skip-modules' => FALSE]): void {
    $this->io()->title('MCP Tools Development Profile');

    // Apply development mode preset.
    $config = $this->configFactory->getEditable('mcp_tools.settings');
    $config->set('mode', 'development');
    $config->set('access.read_only_mode', FALSE);
    $config->set('access.config_only_mode', FALSE);
    $config->set('access.default_scopes', ['read', 'write']);
    $config->set('access.allowed_scopes', ['read', 'write', 'admin']);
    $config->set('access.audit_logging', FALSE);
    $config->set('rate_limiting.enabled', FALSE);
    $config->save();

    $this->io()->success('Applied development mode preset.');

    if (!empty($options['skip-modules'])) {
      $this->io()->text('Skipped module installation (--skip-modules).');
      return;
    }

    // Enable recommended core-only submodules for development.
    $recommendedModules = [
      'mcp_tools_content',
      'mcp_tools_structure',
      'mcp_tools_users',
      'mcp_tools_menus',
      'mcp_tools_views',
      'mcp_tools_blocks',
      'mcp_tools_media',
      'mcp_tools_theme',
      'mcp_tools_config',
      'mcp_tools_cache',
      'mcp_tools_templates',
      'mcp_tools_analysis',
      'mcp_tools_stdio',
    ];

    $toInstall = [];
    foreach ($recommendedModules as $module) {
      if (!$this->moduleHandler->moduleExists($module)) {
        // Check if module exists in the filesystem.
        $moduleInfo = $this->moduleList->getExtensionInfo($module);
        if (!empty($moduleInfo)) {
          $toInstall[] = $module;
        }
      }
    }

    if (empty($toInstall)) {
      $this->io()->text('All recommended submodules are already enabled.');
    }
    else {
      $this->io()->text('Enabling: ' . implode(', ', $toInstall));
      try {
        $this->moduleInstaller->install($toInstall);
        $this->io()->success('Enabled ' . count($toInstall) . ' submodules.');
      }
      catch (\Exception $e) {
        $this->io()->error('Failed to enable modules: ' . $e->getMessage());
        return;
      }
    }

    $toolCount = $this->countMcpTools();
    $this->io()->newLine();
    $this->io()->text("Development profile applied. $toolCount MCP tools now available.");
    $this->io()->text('');
    $this->io()->text('Next steps:');
    $this->io()->text('  1) Start STDIO server: drush mcp-tools:serve --uid=1');
    $this->io()->text('  2) Configure your MCP client (see docs/CLIENT_INTEGRATIONS.md)');
    $this->io()->text('  3) Run drush mcp:status to verify configuration');
  }

  /**
   * Generate MCP client configuration JSON for AI editors.
   */
  #[CLI\Command(name: 'mcp-tools:client-config', aliases: ['mcp-client-config'])]
  #[CLI\Usage(name: 'drush mcp-tools:client-config', description: 'Generate MCP client config JSON')]
  #[CLI\Usage(name: 'drush mcp-tools:client-config --scope=read', description: 'Generate read-only config')]
  #[CLI\Usage(name: 'drush mcp-tools:client-config > .mcp.json', description: 'Save config directly to file')]
  #[CLI\Option(name: 'scope', description: 'Scopes for the MCP server (default: read,write)')]
  #[CLI\Option(name: 'uid', description: 'Drupal user ID for tool execution (default: 1)')]
  public function clientConfig(array $options = ['scope' => 'read,write', 'uid' => '1']): void {
    $scope = is_string($options['scope']) ? $options['scope'] : 'read,write';
    $uid = is_string($options['uid']) ? $options['uid'] : '1';

    $drupalRoot = \Drupal::root();
    $isDdev = (bool) getenv('IS_DDEV_PROJECT');
    $isLando = (bool) getenv('LANDO');

    $generator = new ClientConfigGenerator();
    $config = $generator->buildConfig($drupalRoot, $isDdev, $isLando, $scope, $uid);

    // JSON to stdout (pipeable to file).
    $this->output()->write(json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    // Instructions to stderr (not captured when piping).
    fwrite(\STDERR, "\nSave this as one of:\n");
    fwrite(\STDERR, "  Claude Code:    .mcp.json (project root)\n");
    fwrite(\STDERR, "  Claude Desktop: ~/Library/Application Support/Claude/claude_desktop_config.json\n");
    fwrite(\STDERR, "  Cursor:         .cursor/mcp.json (project root)\n");
    fwrite(\STDERR, "  Windsurf:       .windsurf/mcp.json (project root)\n");
  }

  /**
   * Count MCP tools from the tool manager.
   *
   * @return int
   *   Number of MCP tools available.
   */
  private function countMcpTools(): int {
    $definitions = $this->toolManager->getDefinitions();
    $count = 0;

    foreach ($definitions as $definition) {
      if (!$definition instanceof ToolDefinition) {
        continue;
      }
      $provider = $definition->getProvider() ?? '';
      if (is_string($provider) && str_starts_with($provider, 'mcp_tools')) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Returns allowed Tool API plugin definitions for a server profile.
   *
   * @return array<string, \Drupal\tool\Tool\ToolDefinition>
   */
  private function getAllowedTools(bool $includeAllTools, string $providerPrefix = 'mcp_tools'): array {
    $definitions = $this->toolManager->getDefinitions();
    $allowed = [];

    foreach ($definitions as $id => $definition) {
      if (!$definition instanceof ToolDefinition) {
        continue;
      }
      $provider = $definition->getProvider() ?? '';
      if (!$includeAllTools && (!is_string($provider) || !str_starts_with($provider, $providerPrefix))) {
        continue;
      }
      $allowed[(string) $id] = $definition;
    }

    return $allowed;
  }

  /**
   * Deduplicates component lists by key.
   *
   * @param array<int, array<string, mixed>> $items
   * @param string $key
   *
   * @return array<int, array<string, mixed>>
   */
  private function dedupeListByKey(array $items, string $key): array {
    $seen = [];
    $deduped = [];

    foreach ($items as $item) {
      $value = $item[$key] ?? NULL;
      if (!is_string($value) || $value === '') {
        continue;
      }
      if (isset($seen[$value])) {
        continue;
      }
      $seen[$value] = TRUE;
      $deduped[] = $item;
    }

    return $deduped;
  }

  /**
   * Resolve the template directory for scaffold generation.
   */
  private function getScaffoldTemplateRoot(): ?string {
    $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'scaffold';
    $path = $base . DIRECTORY_SEPARATOR . 'hook';
    if (!is_dir($path)) {
      return NULL;
    }

    return $path;
  }

  /**
   * Render scaffold templates into the destination module directory.
   *
   * @return string[]
   *   List of generated file paths.
   */
  private function renderScaffoldTemplates(string $templateRoot, string $destination, array $replacements, bool $force, string $machineName): array {
    $created = [];

    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($templateRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
      if (!$file->isFile()) {
        continue;
      }

      $relativePath = substr($file->getPathname(), strlen($templateRoot) + 1);
      $relativePath = str_replace('MODULE', $machineName, $relativePath);
      if (str_ends_with($relativePath, '.tpl')) {
        $relativePath = substr($relativePath, 0, -4);
      }

      $targetPath = $destination . DIRECTORY_SEPARATOR . $relativePath;
      $targetDir = dirname($targetPath);

      if (!is_dir($targetDir) && !mkdir($targetDir, 0775, TRUE) && !is_dir($targetDir)) {
        $this->io()->error('Failed to create directory: ' . $targetDir);
        return [];
      }

      if (file_exists($targetPath) && !$force) {
        $this->io()->error('File already exists: ' . $targetPath);
        return [];
      }

      $contents = file_get_contents($file->getPathname());
      if ($contents === FALSE) {
        $this->io()->error('Failed to read template: ' . $file->getPathname());
        return [];
      }

      $rendered = str_replace(array_keys($replacements), array_values($replacements), $contents);
      if (file_put_contents($targetPath, $rendered) === FALSE) {
        $this->io()->error('Failed to write file: ' . $targetPath);
        return [];
      }

      $created[] = $targetPath;
    }

    return $created;
  }

}
