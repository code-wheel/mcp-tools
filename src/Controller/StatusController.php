<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\RateLimiter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for MCP Tools status page.
 */
class StatusController extends ControllerBase {

  public function __construct(
    protected AccessManager $accessManager,
    protected RateLimiter $rateLimiter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mcp_tools.access_manager'),
      $container->get('mcp_tools.rate_limiter'),
    );
  }

  /**
   * Build the status page.
   *
   * @return array
   *   Render array.
   */
  public function status(): array {
    $build = [];

    // Current access status.
    $build['access'] = [
      '#type' => 'details',
      '#title' => $this->t('Access Status'),
      '#open' => TRUE,
    ];

    $accessItems = [
      $this->t('Read-only mode: @status', [
        '@status' => $this->accessManager->isReadOnlyMode() ? $this->t('ENABLED') : $this->t('Disabled'),
      ]),
      $this->t('Config-only mode: @status', [
        '@status' => $this->accessManager->isConfigOnlyMode() ? $this->t('ENABLED') : $this->t('Disabled'),
      ]),
      $this->t('Current scopes: @scopes', [
        '@scopes' => implode(', ', $this->accessManager->getCurrentScopes()),
      ]),
      $this->t('Can read: @status', [
        '@status' => $this->accessManager->canRead() ? $this->t('Yes') : $this->t('No'),
      ]),
      $this->t('Can write: @status', [
        '@status' => $this->accessManager->canWrite() ? $this->t('Yes') : $this->t('No'),
      ]),
      $this->t('Can admin: @status', [
        '@status' => $this->accessManager->canAdmin() ? $this->t('Yes') : $this->t('No'),
      ]),
    ];

    $build['access']['list'] = [
      '#theme' => 'item_list',
      '#items' => $accessItems,
    ];

    // Rate limiting status.
    $build['rate_limits'] = [
      '#type' => 'details',
      '#title' => $this->t('Rate Limiting Status'),
      '#open' => TRUE,
    ];

    $rateStatus = $this->rateLimiter->getStatus();
    $rateItems = [];

    if (!$rateStatus['enabled']) {
      $rateItems[] = $this->t('Rate limiting is DISABLED');
    }
    else {
      $rateItems[] = $this->t('Rate limiting is ENABLED');
      $rateItems[] = $this->t('Client identifier: @id', ['@id' => $rateStatus['client_id']]);

      foreach ($rateStatus['current_usage'] as $type => $usage) {
        $rateItems[] = $this->t('@type: @minute/min, @hour/hour', [
          '@type' => ucfirst(str_replace('_', ' ', $type)),
          '@minute' => $usage['minute'] ?? 0,
          '@hour' => $usage['hour'] ?? 0,
        ]);
      }
    }

    $build['rate_limits']['list'] = [
      '#theme' => 'item_list',
      '#items' => $rateItems,
    ];

    // Enabled submodules - grouped by category.
    $build['modules'] = [
      '#type' => 'details',
      '#title' => $this->t('Enabled Submodules'),
      '#open' => TRUE,
    ];

    // Core-only submodules (no contrib dependencies).
    $coreSubmodules = [
      'mcp_tools_content' => $this->t('Content CRUD'),
      'mcp_tools_structure' => $this->t('Content types, fields, roles, taxonomy'),
      'mcp_tools_users' => $this->t('User management'),
      'mcp_tools_menus' => $this->t('Menu management'),
      'mcp_tools_views' => $this->t('Views management'),
      'mcp_tools_blocks' => $this->t('Block placement'),
      'mcp_tools_media' => $this->t('Media management'),
      'mcp_tools_theme' => $this->t('Theme settings'),
      'mcp_tools_layout_builder' => $this->t('Layout Builder'),
      'mcp_tools_recipes' => $this->t('Drupal Recipes'),
      'mcp_tools_config' => $this->t('Configuration management'),
      'mcp_tools_cache' => $this->t('Cache management'),
      'mcp_tools_cron' => $this->t('Cron management'),
      'mcp_tools_batch' => $this->t('Batch operations'),
      'mcp_tools_templates' => $this->t('Site templates'),
      'mcp_tools_migration' => $this->t('Content migration'),
      'mcp_tools_analysis' => $this->t('Site analysis'),
      'mcp_tools_moderation' => $this->t('Content moderation'),
      'mcp_tools_image_styles' => $this->t('Image styles'),
      'mcp_tools_jsonapi' => $this->t('Generic entity CRUD via JSON:API'),
    ];

    // Contrib-dependent submodules.
    $contribSubmodules = [
      'mcp_tools_webform' => $this->t('Webform (requires webform)'),
      'mcp_tools_paragraphs' => $this->t('Paragraphs (requires paragraphs)'),
      'mcp_tools_redirect' => $this->t('Redirects (requires redirect)'),
      'mcp_tools_pathauto' => $this->t('Path auto (requires pathauto)'),
      'mcp_tools_metatag' => $this->t('Metatag (requires metatag)'),
      'mcp_tools_scheduler' => $this->t('Scheduler (requires scheduler)'),
      'mcp_tools_search_api' => $this->t('Search API (requires search_api)'),
      'mcp_tools_sitemap' => $this->t('Sitemap (requires simple_sitemap)'),
      'mcp_tools_entity_clone' => $this->t('Entity clone (requires entity_clone)'),
      'mcp_tools_ultimate_cron' => $this->t('Ultimate Cron (requires ultimate_cron)'),
    ];

    // Infrastructure submodules.
    $infraSubmodules = [
      'mcp_tools_stdio' => $this->t('STDIO transport'),
      'mcp_tools_remote' => $this->t('HTTP transport'),
      'mcp_tools_observability' => $this->t('Event logging'),
      'mcp_tools_mcp_server' => $this->t('MCP Server bridge (requires mcp_server)'),
    ];

    $build['modules']['core_heading'] = [
      '#markup' => '<strong>' . $this->t('Core-only submodules:') . '</strong>',
    ];

    $coreItems = [];
    foreach ($coreSubmodules as $module => $description) {
      $enabled = $this->moduleHandler()->moduleExists($module);
      $coreItems[] = [
        '#markup' => ($enabled ? '✓' : '✗') . ' ' . $module . ' - ' . $description,
      ];
    }
    $build['modules']['core_list'] = [
      '#theme' => 'item_list',
      '#items' => $coreItems,
    ];

    $build['modules']['contrib_heading'] = [
      '#markup' => '<strong>' . $this->t('Contrib-dependent submodules:') . '</strong>',
    ];

    $contribItems = [];
    foreach ($contribSubmodules as $module => $description) {
      $enabled = $this->moduleHandler()->moduleExists($module);
      $contribItems[] = [
        '#markup' => ($enabled ? '✓' : '✗') . ' ' . $module . ' - ' . $description,
      ];
    }
    $build['modules']['contrib_list'] = [
      '#theme' => 'item_list',
      '#items' => $contribItems,
    ];

    $build['modules']['infra_heading'] = [
      '#markup' => '<strong>' . $this->t('Infrastructure submodules:') . '</strong>',
    ];

    $infraItems = [];
    foreach ($infraSubmodules as $module => $description) {
      $enabled = $this->moduleHandler()->moduleExists($module);
      $infraItems[] = [
        '#markup' => ($enabled ? '✓' : '✗') . ' ' . $module . ' - ' . $description,
      ];
    }
    $build['modules']['infra_list'] = [
      '#theme' => 'item_list',
      '#items' => $infraItems,
    ];

    // Remote HTTP endpoint status (optional submodule).
    if ($this->moduleHandler()->moduleExists('mcp_tools_remote')) {
      $remoteConfig = $this->config('mcp_tools_remote.settings');
      $enabled = (bool) $remoteConfig->get('enabled');
      $uid = (int) ($remoteConfig->get('uid') ?? 0);
      $allowedIps = $remoteConfig->get('allowed_ips') ?? [];
      if (!is_array($allowedIps)) {
        $allowedIps = [];
      }
      $allowedIps = array_values(array_filter(array_map('trim', $allowedIps)));

      $build['remote'] = [
        '#type' => 'details',
        '#title' => $this->t('Remote HTTP Endpoint (Experimental)'),
        '#open' => FALSE,
      ];

      $remoteItems = [
        $this->t('Enabled: @status', ['@status' => $enabled ? $this->t('YES') : $this->t('No')]),
        $this->t('Endpoint: @path', ['@path' => '/_mcp_tools']),
        $this->t('Execution user (uid): @uid', ['@uid' => $uid ?: $this->t('Not set')]),
        $this->t('IP allowlist entries: @count', ['@count' => count($allowedIps)]),
        $this->t('Expose all Tool API tools: @status', [
          '@status' => $remoteConfig->get('include_all_tools') ? $this->t('YES') : $this->t('No'),
        ]),
        [
          '#markup' => $this->t('Configure at <a href=":url">/admin/config/services/mcp-tools/remote</a>.', [
            ':url' => '/admin/config/services/mcp-tools/remote',
          ]),
        ],
      ];

      $build['remote']['list'] = [
        '#theme' => 'item_list',
        '#items' => $remoteItems,
      ];
    }

    // Security recommendations.
    $build['security'] = [
      '#type' => 'details',
      '#title' => $this->t('Security Recommendations'),
      '#open' => TRUE,
    ];

    $warnings = [];
    $config = $this->config('mcp_tools.settings');

    if (!$config->get('access.read_only_mode') && !$config->get('access.config_only_mode')) {
      $warnings[] = [
        '#markup' => '<span style="color: orange;">⚠</span> ' . $this->t('Read-only mode is disabled. Enable read-only or config-only mode for production environments.'),
      ];
    }

    if (!$config->get('rate_limiting.enabled')) {
      $warnings[] = [
        '#markup' => '<span style="color: orange;">⚠</span> ' . $this->t('Rate limiting is disabled. Enable to prevent DoS attacks.'),
      ];
    }

    if (!$config->get('access.audit_logging')) {
      $warnings[] = [
        '#markup' => '<span style="color: orange;">⚠</span> ' . $this->t('Audit logging is disabled. Enable for security monitoring.'),
      ];
    }

    $scopes = $config->get('access.default_scopes') ?? [];
    if (in_array('write', $scopes) || in_array('admin', $scopes)) {
      $warnings[] = [
        '#markup' => '<span style="color: orange;">⚠</span> ' . $this->t('Write/admin scopes enabled by default. Consider read-only for production.'),
      ];
    }

    if ($this->moduleHandler()->moduleExists('mcp_tools_remote')) {
      $remoteConfig = $this->config('mcp_tools_remote.settings');
      if ((bool) $remoteConfig->get('enabled')) {
        $warnings[] = [
          '#markup' => '<span style="color: orange;">⚠</span> ' . $this->t('Remote HTTP endpoint is enabled. Ensure this is only exposed on trusted networks.'),
        ];

        $uid = (int) ($remoteConfig->get('uid') ?? 0);
        if ($uid === 1) {
          $allowUid1 = (bool) $remoteConfig->get('allow_uid1');
          $warnings[] = [
            '#markup' => '<span style="color: orange;">⚠</span> ' . ($allowUid1
              ? $this->t('Remote endpoint is configured to run as uid 1 with the override enabled. Use a dedicated service account for production.')
              : $this->t('Remote endpoint is configured to run as uid 1. Execution will be blocked unless "Use site admin (uid 1)" is enabled in remote settings.')),
          ];
        }

        $allowedIps = $remoteConfig->get('allowed_ips') ?? [];
        if (!is_array($allowedIps)) {
          $allowedIps = [];
        }
        $allowedIps = array_values(array_filter(array_map('trim', $allowedIps)));
        if (empty($allowedIps)) {
          $warnings[] = [
            '#markup' => '<span style="color: orange;">⚠</span> ' . $this->t('Remote endpoint IP allowlist is empty. Set allowed IPs/CIDRs to reduce exposure risk.'),
          ];
        }

        if ((bool) $remoteConfig->get('include_all_tools')) {
          $warnings[] = [
            '#markup' => '<span style="color: orange;">⚠</span> ' . $this->t('Remote endpoint is configured to expose all Tool API tools. Disable this unless you fully trust all installed Tool API providers.'),
          ];
        }
      }
    }

    if (empty($warnings)) {
      $warnings[] = [
        '#markup' => '<span style="color: green;">✓</span> ' . $this->t('All security recommendations met.'),
      ];
    }

    $build['security']['list'] = [
      '#theme' => 'item_list',
      '#items' => $warnings,
    ];

    return $build;
  }

}
