<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure MCP Tools settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mcp_tools_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['mcp_tools.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('mcp_tools.settings');

    // Access Control section.
    $form['access'] = [
      '#type' => 'details',
      '#title' => $this->t('Access Control'),
      '#open' => TRUE,
    ];

    $form['access']['read_only_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Global read-only mode'),
      '#description' => $this->t('When enabled, ALL write operations are blocked site-wide. <strong>Recommended for production environments.</strong>'),
      '#default_value' => $config->get('access.read_only_mode') ?? FALSE,
    ];

    $form['access']['config_only_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Config-only mode'),
      '#description' => $this->t('When enabled, write tools are restricted to configuration changes (e.g., content types, fields, views). Content mutations (nodes, media, users) and operational actions (cache/cron) are blocked unless explicitly allowed below.'),
      '#default_value' => $config->get('access.config_only_mode') ?? FALSE,
    ];

    $form['access']['config_only_allowed_write_kinds'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed write types in config-only mode'),
      '#description' => $this->t('Recommended: allow "Config" only. Enabling additional types reduces safety.'),
      '#options' => [
        'config' => $this->t('Config - Configuration changes'),
        'ops' => $this->t('Ops - Operational actions (cache/cron/indexing)'),
        'content' => $this->t('Content - Content/entity changes (nodes/media/users)'),
      ],
      '#default_value' => $config->get('access.config_only_allowed_write_kinds') ?? ['config'],
      '#states' => [
        'visible' => [
          ':input[name="config_only_mode"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['access']['default_scopes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Default connection scopes'),
      '#description' => $this->t('Scopes granted when no trusted scope override is present. Overrides (header/query/env) are optional and are always limited by the "Allowed scopes" setting.'),
      '#options' => [
        'read' => $this->t('Read - Allow read operations'),
        'write' => $this->t('Write - Allow write operations (content, structure changes)'),
        'admin' => $this->t('Admin - Allow administrative operations (recipe application)'),
      ],
      '#default_value' => $config->get('access.default_scopes') ?? ['read'],
    ];

    $form['access']['allowed_scopes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed scopes (maximum)'),
      '#description' => $this->t('Maximum scopes that can ever be granted to an MCP connection. Any requested scopes (header/query/env) are intersected with this list. <strong>Recommended:</strong> do not allow "admin" unless strictly needed.'),
      '#options' => [
        'read' => $this->t('Read'),
        'write' => $this->t('Write'),
        'admin' => $this->t('Admin'),
      ],
      '#default_value' => $config->get('access.allowed_scopes') ?? ['read', 'write'],
    ];

    $form['access']['trust_scopes_via_header'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Trust X-MCP-Scope header'),
      '#description' => $this->t('Allow MCP clients to request scopes via the HTTP header. <strong>Unsafe on public endpoints</strong> unless you strip/overwrite this header at a trusted reverse proxy.'),
      '#default_value' => $config->get('access.trust_scopes_via_header') ?? FALSE,
    ];

    $form['access']['trust_scopes_via_query'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Trust mcp_scope query parameter'),
      '#description' => $this->t('Allow MCP clients to request scopes via URL query parameter. <strong>Not recommended</strong> except for local development.'),
      '#default_value' => $config->get('access.trust_scopes_via_query') ?? FALSE,
    ];

    $form['access']['trust_scopes_via_env'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Trust MCP_SCOPE environment variable'),
      '#description' => $this->t('Allow scope selection via the MCP_SCOPE environment variable (primarily for Drush/STDIO transport).'),
      '#default_value' => $config->get('access.trust_scopes_via_env') ?? TRUE,
    ];

    $form['access']['audit_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable audit logging'),
      '#description' => $this->t('Log all MCP operations to the watchdog. Recommended for security auditing.'),
      '#default_value' => $config->get('access.audit_logging') ?? TRUE,
    ];

    // Rate Limiting section.
    $form['rate_limiting'] = [
      '#type' => 'details',
      '#title' => $this->t('Rate Limiting'),
      '#description' => $this->t('Protect against DoS attacks and runaway AI operations by limiting write operations per client.'),
      '#open' => TRUE,
    ];

    $form['rate_limiting']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable rate limiting'),
      '#description' => $this->t('When enabled, write operations are limited per client. <strong>Recommended for production.</strong>'),
      '#default_value' => $config->get('rate_limiting.enabled') ?? FALSE,
    ];

    $form['rate_limiting']['trust_client_id_header'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Trust X-MCP-Client-Id header for rate limiting'),
      '#description' => $this->t('When enabled, rate limiting will bucket by IP + X-MCP-Client-Id. Leave disabled unless you control the clients (header spoofing can bypass per-client limits).'),
      '#default_value' => $config->get('rate_limiting.trust_client_id_header') ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['rate_limiting']['max_writes_per_minute'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum writes per minute'),
      '#description' => $this->t('Maximum write operations allowed per minute per client.'),
      '#default_value' => $config->get('rate_limiting.max_writes_per_minute') ?? 30,
      '#min' => 1,
      '#max' => 1000,
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['rate_limiting']['max_writes_per_hour'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum writes per hour'),
      '#description' => $this->t('Maximum write operations allowed per hour per client.'),
      '#default_value' => $config->get('rate_limiting.max_writes_per_hour') ?? 500,
      '#min' => 1,
      '#max' => 10000,
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['rate_limiting']['max_deletes_per_hour'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum deletes per hour'),
      '#description' => $this->t('Maximum delete operations allowed per hour per client. Delete operations are more dangerous.'),
      '#default_value' => $config->get('rate_limiting.max_deletes_per_hour') ?? 50,
      '#min' => 1,
      '#max' => 1000,
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['rate_limiting']['max_structure_changes_per_hour'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum structure changes per hour'),
      '#description' => $this->t('Maximum structural changes (content types, fields, roles) per hour. These affect site architecture.'),
      '#default_value' => $config->get('rate_limiting.max_structure_changes_per_hour') ?? 100,
      '#min' => 1,
      '#max' => 1000,
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Read Operation Rate Limits.
    $form['rate_limits'] = [
      '#type' => 'details',
      '#title' => $this->t('Read Operation Limits'),
      '#description' => $this->t('Limits for expensive read operations that could impact site performance.'),
      '#open' => FALSE,
    ];

    $form['rate_limits']['broken_link_max_per_hour'] = [
      '#type' => 'number',
      '#title' => $this->t('Broken link scans per hour'),
      '#default_value' => $config->get('rate_limits.broken_link_scan.max_per_hour') ?? 10,
      '#min' => 1,
      '#max' => 100,
    ];

    $form['rate_limits']['broken_link_max_urls'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum URLs per broken link scan'),
      '#default_value' => $config->get('rate_limits.broken_link_scan.max_urls_per_scan') ?? 500,
      '#min' => 10,
      '#max' => 5000,
    ];

    $form['rate_limits']['content_search_per_minute'] = [
      '#type' => 'number',
      '#title' => $this->t('Content searches per minute'),
      '#default_value' => $config->get('rate_limits.content_search.max_per_minute') ?? 30,
      '#min' => 1,
      '#max' => 100,
    ];

    // Output Settings.
    $form['output'] = [
      '#type' => 'details',
      '#title' => $this->t('Output Settings'),
      '#open' => FALSE,
    ];

    $form['output']['max_items'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum items in list operations'),
      '#description' => $this->t('Maximum number of items returned by list tools.'),
      '#default_value' => $config->get('output.max_items') ?? 100,
      '#min' => 10,
      '#max' => 1000,
    ];

    $form['output']['include_sensitive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include sensitive information'),
      '#description' => $this->t('<strong>Warning:</strong> When enabled, some tools may expose sensitive configuration values. Only enable for trusted environments.'),
      '#default_value' => $config->get('output.include_sensitive') ?? FALSE,
    ];

    // SSRF Protection.
    $form['ssrf'] = [
      '#type' => 'details',
      '#title' => $this->t('SSRF Protection'),
      '#description' => $this->t('Server-Side Request Forgery protection for URL fetching operations.'),
      '#open' => FALSE,
    ];

    $form['ssrf']['allowed_hosts'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed hosts'),
      '#description' => $this->t('One host pattern per line. Supports wildcards (*.example.com). Leave empty to disable URL fetching.'),
      '#default_value' => implode("\n", $config->get('allowed_hosts') ?? ['localhost', '*.local']),
      '#rows' => 5,
    ];

    // Webhook Notifications.
    $form['webhooks'] = [
      '#type' => 'details',
      '#title' => $this->t('Webhook Notifications'),
      '#description' => $this->t('Send notifications to external systems when MCP performs operations. Useful for Slack, audit systems, etc.'),
      '#open' => FALSE,
    ];

    $form['webhooks']['webhooks_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable webhook notifications'),
      '#default_value' => $config->get('webhooks.enabled') ?? FALSE,
    ];

    $form['webhooks']['webhook_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Webhook URL'),
      '#description' => $this->t('URL to POST notifications to. Must be HTTPS in production.'),
      '#default_value' => $config->get('webhooks.url') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="webhooks_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['webhooks']['webhook_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Webhook secret'),
      '#description' => $this->t('Secret for HMAC-SHA256 signing. The signature is sent in the X-MCP-Signature header.'),
      '#default_value' => $config->get('webhooks.secret') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="webhooks_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['webhooks']['webhook_allowed_hosts'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed webhook hosts (optional)'),
      '#description' => $this->t('One host pattern per line (e.g., hooks.slack.com, *.example.com). When empty, any public host is allowed. Private networks and metadata services are always blocked.'),
      '#default_value' => implode("\n", $config->get('webhooks.allowed_hosts') ?? []),
      '#rows' => 4,
      '#states' => [
        'visible' => [
          ':input[name="webhooks_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['webhooks']['webhook_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Request timeout (seconds)'),
      '#default_value' => $config->get('webhooks.timeout') ?? 5,
      '#min' => 1,
      '#max' => 30,
      '#states' => [
        'visible' => [
          ':input[name="webhooks_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['webhooks']['batch_notifications'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Batch notifications'),
      '#description' => $this->t('Queue notifications and send in batches for better performance.'),
      '#default_value' => $config->get('webhooks.batch_notifications') ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="webhooks_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['webhooks']['notify_on'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Notify on operations'),
      '#options' => [
        'create' => $this->t('Create - New entities created'),
        'update' => $this->t('Update - Existing entities modified'),
        'delete' => $this->t('Delete - Entities removed'),
        'structure' => $this->t('Structure - Content types, fields, roles changed'),
      ],
      '#default_value' => $config->get('webhooks.notify_on') ?? ['create', 'update', 'delete', 'structure'],
      '#states' => [
        'visible' => [
          ':input[name="webhooks_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Production Warning.
    $form['production_warning'] = [
      '#type' => 'markup',
      '#markup' => '<div class="messages messages--warning">' .
        '<h3>' . $this->t('Production Environment Warning') . '</h3>' .
        '<p>' . $this->t('MCP Tools is designed primarily for <strong>local development and prototyping</strong>. If you must use it in production:') . '</p>' .
        '<ul>' .
        '<li>' . $this->t('Enable read-only mode') . '</li>' .
        '<li>' . $this->t('Or enable config-only mode') . '</li>' .
        '<li>' . $this->t('Enable rate limiting') . '</li>' .
        '<li>' . $this->t('Enable audit logging') . '</li>' .
        '<li>' . $this->t('Restrict default scopes to "read" only') . '</li>' .
        '<li>' . $this->t('Use IP allowlisting at the web server level') . '</li>' .
        '<li>' . $this->t('Monitor audit logs regularly') . '</li>' .
        '</ul>' .
        '</div>',
      '#weight' => -100,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('mcp_tools.settings');

    // Access settings.
    $config->set('access.read_only_mode', (bool) $form_state->getValue('read_only_mode'));
    $config->set('access.config_only_mode', (bool) $form_state->getValue('config_only_mode'));
    $kinds = array_filter($form_state->getValue('config_only_allowed_write_kinds') ?? []);
    if (empty($kinds)) {
      $kinds = ['config'];
    }
    $config->set('access.config_only_allowed_write_kinds', array_values($kinds));

    $allowedScopes = array_filter($form_state->getValue('allowed_scopes') ?? []);
    if (empty($allowedScopes)) {
      $allowedScopes = ['read'];
    }
    $config->set('access.allowed_scopes', array_values($allowedScopes));

    $scopes = array_filter($form_state->getValue('default_scopes'));
    // Ensure defaults cannot exceed allowed scopes.
    $defaultScopes = array_values(array_intersect($scopes, $allowedScopes));
    if (empty($defaultScopes)) {
      $defaultScopes = array_values($allowedScopes);
    }
    $config->set('access.default_scopes', $defaultScopes);

    $config->set('access.trust_scopes_via_header', (bool) $form_state->getValue('trust_scopes_via_header'));
    $config->set('access.trust_scopes_via_query', (bool) $form_state->getValue('trust_scopes_via_query'));
    $config->set('access.trust_scopes_via_env', (bool) $form_state->getValue('trust_scopes_via_env'));

    $config->set('access.audit_logging', (bool) $form_state->getValue('audit_logging'));

    // Rate limiting settings.
    $config->set('rate_limiting.enabled', (bool) $form_state->getValue('enabled'));
    $config->set('rate_limiting.trust_client_id_header', (bool) $form_state->getValue('trust_client_id_header'));
    $config->set('rate_limiting.max_writes_per_minute', (int) $form_state->getValue('max_writes_per_minute'));
    $config->set('rate_limiting.max_writes_per_hour', (int) $form_state->getValue('max_writes_per_hour'));
    $config->set('rate_limiting.max_deletes_per_hour', (int) $form_state->getValue('max_deletes_per_hour'));
    $config->set('rate_limiting.max_structure_changes_per_hour', (int) $form_state->getValue('max_structure_changes_per_hour'));

    // Read rate limits.
    $config->set('rate_limits.broken_link_scan.max_per_hour', (int) $form_state->getValue('broken_link_max_per_hour'));
    $config->set('rate_limits.broken_link_scan.max_urls_per_scan', (int) $form_state->getValue('broken_link_max_urls'));
    $config->set('rate_limits.content_search.max_per_minute', (int) $form_state->getValue('content_search_per_minute'));

    // Output settings.
    $config->set('output.max_items', (int) $form_state->getValue('max_items'));
    $config->set('output.include_sensitive', (bool) $form_state->getValue('include_sensitive'));

    // SSRF settings.
    $hosts = array_filter(array_map('trim', explode("\n", $form_state->getValue('allowed_hosts'))));
    $config->set('allowed_hosts', $hosts);

    // Webhook settings.
    $config->set('webhooks.enabled', (bool) $form_state->getValue('webhooks_enabled'));
    $config->set('webhooks.url', $form_state->getValue('webhook_url') ?? '');
    $config->set('webhooks.secret', $form_state->getValue('webhook_secret') ?? '');
    $webhookHosts = array_filter(array_map('trim', explode("\n", (string) $form_state->getValue('webhook_allowed_hosts'))));
    $config->set('webhooks.allowed_hosts', $webhookHosts);
    $config->set('webhooks.timeout', (int) $form_state->getValue('webhook_timeout'));
    $config->set('webhooks.batch_notifications', (bool) $form_state->getValue('batch_notifications'));
    $notifyOn = array_filter($form_state->getValue('notify_on') ?? []);
    $config->set('webhooks.notify_on', array_values($notifyOn));

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
