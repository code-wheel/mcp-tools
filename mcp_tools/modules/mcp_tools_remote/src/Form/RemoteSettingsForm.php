<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mcp_tools_remote\Service\ApiKeyManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for the MCP Tools remote HTTP endpoint.
 */
final class RemoteSettingsForm extends ConfigFormBase {

  public function __construct(
    private readonly ApiKeyManager $apiKeyManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mcp_tools_remote.api_key_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mcp_tools_remote_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['mcp_tools_remote.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('mcp_tools_remote.settings');

    $form['warning'] = [
      '#type' => 'markup',
      '#markup' => '<div class="messages messages--warning"><p>' .
        $this->t('This module is experimental and should only be used on trusted internal networks. Prefer the STDIO transport (`mcp_tools_stdio`) for local development.') .
        '</p></div>',
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable HTTP endpoint'),
      '#description' => $this->t('Expose MCP Tools at <code>/_mcp_tools</code>.'),
      '#default_value' => (bool) $config->get('enabled'),
    ];

    $form['uid'] = [
      '#type' => 'number',
      '#title' => $this->t('Execution user ID'),
      '#description' => $this->t('Tools will execute as this Drupal user (for attribution and access context). Recommended: use a dedicated service account (avoid uid 1) with only the required MCP Tools permissions.'),
      '#default_value' => (int) ($config->get('uid') ?? 1),
      '#min' => 1,
    ];

    $form['server_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server name'),
      '#default_value' => (string) ($config->get('server_name') ?? 'Drupal MCP Tools'),
    ];

    $form['server_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server version'),
      '#default_value' => (string) ($config->get('server_version') ?? '1.0.0'),
    ];

    $form['pagination_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Pagination limit'),
      '#default_value' => (int) ($config->get('pagination_limit') ?? 50),
      '#min' => 1,
      '#max' => 1000,
    ];

    $form['include_all_tools'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose all Tool API tools'),
      '#description' => $this->t('When disabled (recommended), only tools whose provider starts with <code>mcp_tools</code> are exposed.'),
      '#default_value' => (bool) $config->get('include_all_tools'),
    ];

    $form['keys'] = [
      '#type' => 'details',
      '#title' => $this->t('API keys'),
      '#open' => TRUE,
    ];

    $form['keys']['help'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Manage keys via Drush:') . '</p><pre><code>' .
        "drush mcp-tools:remote-key-create --label=\"My Key\" --scopes=read\n" .
        "drush mcp-tools:remote-key-list\n" .
        "drush mcp-tools:remote-key-revoke KEY_ID\n" .
        '</code></pre>',
    ];

    $keys = $this->apiKeyManager->listKeys();
    $rows = [];
    foreach ($keys as $id => $data) {
      $rows[] = [
        'id' => $id,
        'label' => $data['label'] ?? '',
        'scopes' => implode(',', $data['scopes'] ?? []),
        'created' => $data['created'] ?? '',
        'last_used' => $data['last_used'] ?? '',
      ];
    }

    $form['keys']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('ID'),
        $this->t('Label'),
        $this->t('Scopes'),
        $this->t('Created'),
        $this->t('Last used'),
      ],
      '#rows' => array_map(static fn(array $row): array => array_values($row), $rows),
      '#empty' => $this->t('No keys found.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('mcp_tools_remote.settings')
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('uid', (int) $form_state->getValue('uid'))
      ->set('server_name', (string) $form_state->getValue('server_name'))
      ->set('server_version', (string) $form_state->getValue('server_version'))
      ->set('pagination_limit', (int) $form_state->getValue('pagination_limit'))
      ->set('include_all_tools', (bool) $form_state->getValue('include_all_tools'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
