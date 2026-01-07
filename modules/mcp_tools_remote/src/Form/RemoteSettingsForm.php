<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mcp_tools_remote\Service\ApiKeyManager;
use Drupal\user\PermissionHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for the MCP Tools remote HTTP endpoint.
 */
final class RemoteSettingsForm extends ConfigFormBase {

  public function __construct(
    private readonly ApiKeyManager $apiKeyManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PermissionHandlerInterface $permissionHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mcp_tools_remote.api_key_manager'),
      $container->get('entity_type.manager'),
      $container->get('user.permissions'),
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
        $this->t('This module is experimental and should only be used on trusted internal networks. Prefer the STDIO transport (<code>mcp_tools_stdio</code>) for local development.') .
        '</p></div>',
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable HTTP endpoint'),
      '#description' => $this->t('Expose MCP Tools at <code>/_mcp_tools</code>.'),
      '#default_value' => (bool) $config->get('enabled'),
    ];

    $form['allowed_ips'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed client IPs (optional)'),
      '#description' => $this->t('One per line. Examples: <code>127.0.0.1</code>, <code>10.0.0.0/8</code>. Leave empty to allow any IP.'),
      '#default_value' => implode("\n", $config->get('allowed_ips') ?? []),
      '#rows' => 3,
    ];

    $form['allowed_origins'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed origins (optional)'),
      '#description' => $this->t('Defense-in-depth against DNS rebinding. One host per line.'),
      '#default_value' => implode("\n", $config->get('allowed_origins') ?? []),
      '#rows' => 3,
    ];

    // Execution user section.
    $form['execution'] = [
      '#type' => 'details',
      '#title' => $this->t('Execution User'),
      '#open' => TRUE,
    ];

    $form['execution']['uid'] = [
      '#type' => 'number',
      '#title' => $this->t('Execution user ID'),
      '#description' => $this->t('Tools execute as this Drupal user. Use a dedicated service account (uid 2+).'),
      '#default_value' => (int) ($config->get('uid') ?? 1),
      '#min' => 1,
    ];

    // Show current user status.
    $uid = (int) ($config->get('uid') ?? 1);
    if ($uid > 1) {
      $user = $this->entityTypeManager->getStorage('user')->load($uid);
      if ($user) {
        $mcp_permissions = $this->getUserMcpPermissions($user);
        $count = count($mcp_permissions);

        if ($count === 0) {
          $form['execution']['status'] = [
            '#type' => 'markup',
            '#markup' => '<div class="messages messages--error">' .
              $this->t('User "@name" has no MCP permissions. <a href=":url">Set up permissions</a>.', [
                '@name' => $user->getAccountName(),
                ':url' => Url::fromRoute('mcp_tools.permissions')->toString(),
              ]) . '</div>',
          ];
        }
        else {
          $form['execution']['status'] = [
            '#type' => 'markup',
            '#markup' => '<div class="messages messages--status">' .
              $this->t('User "@name" has @count MCP permission(s). <a href=":url">View details</a>.', [
                '@name' => $user->getAccountName(),
                '@count' => $count,
                ':url' => Url::fromRoute('mcp_tools.permissions')->toString(),
              ]) . '</div>',
          ];
        }
      }
      else {
        $form['execution']['status'] = [
          '#type' => 'markup',
          '#markup' => '<div class="messages messages--error">' .
            $this->t('User @uid does not exist.', ['@uid' => $uid]) . '</div>',
        ];
      }
    }
    else {
      $form['execution']['status'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">' .
          $this->t('Configure a dedicated execution user (uid 2+). <a href=":url">Set up permissions</a> first.', [
            ':url' => Url::fromRoute('mcp_tools.permissions')->toString(),
          ]) . '</div>',
      ];
    }

    // Server settings.
    $form['server'] = [
      '#type' => 'details',
      '#title' => $this->t('Server Settings'),
      '#open' => FALSE,
    ];

    $form['server']['server_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server name'),
      '#default_value' => (string) ($config->get('server_name') ?? 'Drupal MCP Tools'),
    ];

    $form['server']['server_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server version'),
      '#default_value' => (string) ($config->get('server_version') ?? '1.0.0'),
    ];

    $form['server']['pagination_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Pagination limit'),
      '#default_value' => (int) ($config->get('pagination_limit') ?? 50),
      '#min' => 1,
      '#max' => 1000,
    ];

    $form['server']['include_all_tools'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose all Tool API tools'),
      '#description' => $this->t('When disabled, only MCP Tools are exposed.'),
      '#default_value' => (bool) $config->get('include_all_tools'),
    ];

    // API Keys section.
    $form['keys'] = [
      '#type' => 'details',
      '#title' => $this->t('API Keys'),
      '#open' => TRUE,
    ];

    $form['keys']['help'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Manage keys via Drush:') . '</p><pre><code>' .
        "drush mcp-tools:remote-key-create --label=\"My Key\" --scopes=read,write\n" .
        "drush mcp-tools:remote-key-list\n" .
        "drush mcp-tools:remote-key-revoke KEY_ID" .
        '</code></pre>',
    ];

    $keys = $this->apiKeyManager->listKeys();
    $rows = [];
    foreach ($keys as $id => $data) {
      $rows[] = [
        $id,
        $data['label'] ?? '',
        implode(', ', $data['scopes'] ?? []),
        $data['created'] ?? '',
        $data['last_used'] ?? '-',
        $data['expires'] ?? 'never',
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
        $this->t('Expires'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No API keys. Create one using the Drush command above.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    if ((bool) $form_state->getValue('enabled') && (int) $form_state->getValue('uid') === 1) {
      $form_state->setErrorByName('uid', $this->t('Do not run the remote endpoint as uid 1. Create a dedicated service account.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $allowedIps = preg_split('/\\R+/', (string) $form_state->getValue('allowed_ips'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $allowedIps = array_values(array_filter(array_map('trim', $allowedIps)));

    $allowedOrigins = preg_split('/\\R+/', (string) $form_state->getValue('allowed_origins'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $allowedOrigins = array_values(array_filter(array_map('trim', $allowedOrigins)));

    $this->config('mcp_tools_remote.settings')
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('uid', (int) $form_state->getValue('uid'))
      ->set('allowed_ips', $allowedIps)
      ->set('allowed_origins', $allowedOrigins)
      ->set('server_name', (string) $form_state->getValue('server_name'))
      ->set('server_version', (string) $form_state->getValue('server_version'))
      ->set('pagination_limit', (int) $form_state->getValue('pagination_limit'))
      ->set('include_all_tools', (bool) $form_state->getValue('include_all_tools'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Get MCP-related permissions that a user has.
   */
  private function getUserMcpPermissions($user): array {
    $all_permissions = $this->permissionHandler->getPermissions();
    $mcp_permissions = [];

    foreach ($all_permissions as $perm_name => $perm_info) {
      if (str_starts_with($perm_name, 'mcp_tools ') && $user->hasPermission($perm_name)) {
        $mcp_permissions[] = $perm_name;
      }
    }

    return $mcp_permissions;
  }

}
