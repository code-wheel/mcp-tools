<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\mcp_tools_remote\Service\ApiKeyManager;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
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
    private readonly PasswordGeneratorInterface $passwordGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mcp_tools_remote.api_key_manager'),
      $container->get('entity_type.manager'),
      $container->get('user.permissions'),
      $container->get('password_generator'),
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
        $this->t('This module is experimental and should only be used on trusted internal networks. Prefer the STDIO transport (`mcp_tools_stdio`) for local development. Remote execution as uid 1 is blocked at runtime.') .
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
      '#description' => $this->t('When provided, only these IPs/CIDRs may access the endpoint. One per line (examples: <code>127.0.0.1</code>, <code>10.0.0.0/8</code>). Leave empty to allow any IP (not recommended).'),
      '#default_value' => implode("\n", $config->get('allowed_ips') ?? []),
      '#rows' => 4,
    ];

    $form['allowed_origins'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed origins (optional)'),
      '#description' => $this->t('Defense-in-depth against DNS rebinding. When provided, requests must match by <code>Origin</code>/<code>Referer</code>/<code>Host</code>. One host per line (examples: <code>localhost</code>, <code>example.com</code>, <code>*.example.com</code>). Leave empty to allow any origin.'),
      '#default_value' => implode("\n", $config->get('allowed_origins') ?? []),
      '#rows' => 4,
    ];

    // Load the current execution user for the autocomplete default.
    $uid = (int) ($config->get('uid') ?? 0);
    $executionUser = NULL;
    if ($uid > 1) {
      $executionUser = $this->entityTypeManager->getStorage('user')->load($uid);
    }

    // Check if mcp_executor user already exists.
    $existingExecutor = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['name' => 'mcp_executor']);
    $executorExists = !empty($existingExecutor);

    $form['execution_user_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Execution User'),
    ];

    $form['execution_user_wrapper']['execution_user'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select user'),
      '#description' => $this->t('Tools execute as this user for attribution and access control. Use a dedicated service account. <strong>uid 1 is blocked for security.</strong>'),
      '#target_type' => 'user',
      '#selection_settings' => [
        'include_anonymous' => FALSE,
      ],
      '#default_value' => $executionUser,
    ];

    if (!$executionUser && $uid !== 0) {
      $form['execution_user_wrapper']['execution_user']['#description'] .= '<br><strong>' . $this->t('Warning: Previously configured user (uid @uid) no longer exists.', ['@uid' => $uid]) . '</strong>';
    }

    $form['execution_user_wrapper']['use_uid1'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use site admin (uid 1)'),
      '#description' => $this->t('Run tools as the main admin account. Simple for development.'),
      '#default_value' => $uid === 1,
    ];

    if (!$executorExists) {
      $form['execution_user_wrapper']['create_executor'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create MCP Executor Account'),
        '#submit' => ['::createExecutorAccount'],
        '#limit_validation_errors' => [],
        '#attributes' => ['class' => ['button--secondary']],
        '#states' => [
          'visible' => [
            ':input[name="execution_user_wrapper[use_uid1]"]' => ['checked' => FALSE],
          ],
        ],
      ];
      $form['execution_user_wrapper']['create_executor_help'] = [
        '#type' => 'markup',
        '#markup' => '<p class="description">' . $this->t('Or create a dedicated <code>mcp_executor</code> service account.') . '</p>',
        '#states' => [
          'visible' => [
            ':input[name="execution_user_wrapper[use_uid1]"]' => ['checked' => FALSE],
          ],
        ],
      ];
    }
    else {
      $executor = reset($existingExecutor);
      $form['execution_user_wrapper']['executor_exists'] = [
        '#type' => 'markup',
        '#markup' => '<p class="messages messages--status">' . $this->t('The <code>mcp_executor</code> account exists (uid @uid). You can select it above.', ['@uid' => $executor->id()]) . '</p>',
        '#states' => [
          'visible' => [
            ':input[name="execution_user_wrapper[use_uid1]"]' => ['checked' => FALSE],
          ],
        ],
      ];
    }

    // Hide user select when using uid 1.
    $form['execution_user_wrapper']['execution_user']['#states'] = [
      'visible' => [
        ':input[name="execution_user_wrapper[use_uid1]"]' => ['checked' => FALSE],
      ],
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
        "drush mcp-tools:remote-setup\n" .
        "drush mcp-tools:remote-key-create --label=\"My Key\" --scopes=read --ttl=86400\n" .
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
        'expires' => $data['expires'] ?? '',
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
      '#rows' => array_map(static fn(array $row): array => array_values($row), $rows),
      '#empty' => $this->t('No keys found.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $useUid1 = (bool) $form_state->getValue(['execution_user_wrapper', 'use_uid1']);
    $uid = $useUid1 ? 1 : (int) $form_state->getValue(['execution_user_wrapper', 'execution_user']);
    $enabled = (bool) $form_state->getValue('enabled');

    if ($enabled && $uid === 0) {
      $form_state->setErrorByName('execution_user_wrapper][execution_user', $this->t('An execution user is required when the endpoint is enabled.'));
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

    $useUid1 = (bool) $form_state->getValue(['execution_user_wrapper', 'use_uid1']);
    $uid = $useUid1 ? 1 : (int) $form_state->getValue(['execution_user_wrapper', 'execution_user']);

    $this->config('mcp_tools_remote.settings')
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('uid', $uid)
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
   * Submit handler for creating the MCP executor account.
   */
  public function createExecutorAccount(array &$form, FormStateInterface $form_state): void {
    // Create the mcp_executor role if it doesn't exist.
    if (!Role::load('mcp_executor')) {
      $role = Role::create([
        'id' => 'mcp_executor',
        'label' => 'MCP Executor',
      ]);
      $role->grantPermission('access content');

      // Grant all MCP tools permissions.
      $permissions = $this->permissionHandler->getPermissions();
      foreach ($permissions as $permission => $info) {
        if (str_starts_with($permission, 'mcp_tools use ')) {
          $role->grantPermission($permission);
        }
      }
      $role->save();
      $this->messenger()->addStatus($this->t('Created <em>MCP Executor</em> role with all MCP Tools permissions.'));
    }

    // Create the mcp_executor user if it doesn't exist.
    $existing = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['name' => 'mcp_executor']);

    if (empty($existing)) {
      $user = User::create([
        'name' => 'mcp_executor',
        'mail' => 'mcp_executor@localhost.invalid',
        'status' => 1,
        'roles' => ['mcp_executor'],
      ]);
      // Set a random password (user won't log in directly).
      $user->setPassword($this->passwordGenerator->generate(32));
      $user->save();

      // Update config with the new user's ID.
      $this->config('mcp_tools_remote.settings')
        ->set('uid', (int) $user->id())
        ->save();

      $this->messenger()->addStatus($this->t('Created <em>mcp_executor</em> user (uid @uid) and configured as the execution user.', [
        '@uid' => $user->id(),
      ]));
    }
    else {
      $user = reset($existing);
      // Just update the config to use this existing user.
      $this->config('mcp_tools_remote.settings')
        ->set('uid', (int) $user->id())
        ->save();

      $this->messenger()->addStatus($this->t('Configured existing <em>mcp_executor</em> user (uid @uid) as the execution user.', [
        '@uid' => $user->id(),
      ]));
    }
  }

}
