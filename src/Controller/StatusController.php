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

    // Enabled submodules.
    $build['modules'] = [
      '#type' => 'details',
      '#title' => $this->t('Enabled Submodules'),
      '#open' => TRUE,
    ];

    $submodules = [
      'mcp_tools_content' => $this->t('Content CRUD'),
      'mcp_tools_structure' => $this->t('Content types, fields, taxonomy, roles'),
      'mcp_tools_users' => $this->t('User management'),
      'mcp_tools_menus' => $this->t('Menu management'),
      'mcp_tools_views' => $this->t('Views management'),
      'mcp_tools_blocks' => $this->t('Block placement'),
      'mcp_tools_media' => $this->t('Media management'),
      'mcp_tools_webform' => $this->t('Webform integration'),
      'mcp_tools_theme' => $this->t('Theme settings'),
      'mcp_tools_layout_builder' => $this->t('Layout Builder'),
      'mcp_tools_recipes' => $this->t('Drupal Recipes'),
      'mcp_tools_config' => $this->t('Configuration management'),
    ];

    $moduleItems = [];
    foreach ($submodules as $module => $description) {
      $enabled = $this->moduleHandler()->moduleExists($module);
      $moduleItems[] = [
        '#markup' => $this->t('@module: @status - @desc', [
          '@module' => $module,
          '@status' => $enabled ? '✓ Enabled' : '✗ Disabled',
          '@desc' => $description,
        ]),
      ];
    }

    $build['modules']['list'] = [
      '#theme' => 'item_list',
      '#items' => $moduleItems,
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
          $warnings[] = [
            '#markup' => '<span style="color: orange;">⚠</span> ' . $this->t('Remote endpoint is configured to run as uid 1. Use a dedicated service account (uid 1 is blocked at runtime).'),
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
