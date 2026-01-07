<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\PermissionHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Permission overview and role setup form for MCP Tools.
 */
final class PermissionsForm extends FormBase {

  /**
   * Permission categories with descriptions.
   *
   * These match the actual permissions defined by MCP Tools modules.
   */
  private const PERMISSION_CATEGORIES = [
    'site_tools' => [
      'label' => 'Site Tools',
      'description' => 'Site status, system info, watchdog logs',
      'permission' => 'mcp_tools use site_tools',
    ],
    'site_health' => [
      'label' => 'Site Health',
      'description' => 'Health checks, performance metrics',
      'permission' => 'mcp_tools use site_health',
    ],
    'content' => [
      'label' => 'Content',
      'description' => 'Search, create, update, delete content',
      'permission' => 'mcp_tools use content',
    ],
    'structure' => [
      'label' => 'Structure',
      'description' => 'Content types, vocabularies, fields, roles',
      'permission' => 'mcp_tools use structure',
    ],
    'config' => [
      'label' => 'Configuration',
      'description' => 'View and list configuration objects',
      'permission' => 'mcp_tools use config',
    ],
    'cache' => [
      'label' => 'Cache',
      'description' => 'Clear and rebuild caches',
      'permission' => 'mcp_tools use cache',
    ],
    'blocks' => [
      'label' => 'Blocks',
      'description' => 'Place, configure, remove blocks',
      'permission' => 'mcp_tools use blocks',
    ],
    'menus' => [
      'label' => 'Menus',
      'description' => 'Manage menus and menu links',
      'permission' => 'mcp_tools use menus',
    ],
    'users' => [
      'label' => 'Users',
      'description' => 'Create, update, block users',
      'permission' => 'mcp_tools use users',
    ],
    'media' => [
      'label' => 'Media',
      'description' => 'Upload files, manage media',
      'permission' => 'mcp_tools use media',
    ],
    'views' => [
      'label' => 'Views',
      'description' => 'Create, configure, delete views',
      'permission' => 'mcp_tools use views',
    ],
    'analysis' => [
      'label' => 'Analysis',
      'description' => 'SEO, accessibility, content audits',
      'permission' => 'mcp_tools use analysis',
    ],
  ];

  /**
   * Role templates with their permissions.
   */
  private const ROLE_TEMPLATES = [
    'mcp_monitor' => [
      'label' => 'MCP Monitor',
      'description' => 'Read-only monitoring. Site status, health checks, and analysis only.',
      'permissions' => [
        'mcp_tools use site_tools',
        'mcp_tools use site_health',
        'mcp_tools use analysis',
      ],
    ],
    'mcp_editor' => [
      'label' => 'MCP Editor',
      'description' => 'Content management. Can create, edit, and delete content and media.',
      'permissions' => [
        'mcp_tools use site_tools',
        'mcp_tools use site_health',
        'mcp_tools use content',
        'mcp_tools use media',
        'mcp_tools use menus',
        'mcp_tools use analysis',
      ],
    ],
    'mcp_admin' => [
      'label' => 'MCP Admin',
      'description' => 'Full access. All MCP capabilities including structure and cache.',
      'permissions' => [
        'mcp_tools use site_tools',
        'mcp_tools use site_health',
        'mcp_tools use content',
        'mcp_tools use structure',
        'mcp_tools use config',
        'mcp_tools use cache',
        'mcp_tools use blocks',
        'mcp_tools use menus',
        'mcp_tools use users',
        'mcp_tools use media',
        'mcp_tools use views',
        'mcp_tools use analysis',
      ],
    ],
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PermissionHandlerInterface $permissionHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('user.permissions'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mcp_tools_permissions';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#prefix'] = '<div id="mcp-permissions-wrapper">';
    $form['#suffix'] = '</div>';

    // Introduction.
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('MCP Tools uses Drupal\'s permission system. Each tool category requires a specific permission. Use this page to see what permissions exist and quickly create roles for common use cases.') . '</p>',
    ];

    // Permission Categories Reference.
    $form['categories'] = [
      '#type' => 'details',
      '#title' => $this->t('Permission Categories'),
      '#open' => TRUE,
    ];

    $form['categories']['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('These are the MCP permission categories. Grant them to roles via <a href=":url">People > Permissions</a>.', [
        ':url' => Url::fromRoute('user.admin_permissions')->toString() . '#module-mcp_tools',
      ]) . '</p>',
    ];

    $category_rows = [];
    foreach (self::PERMISSION_CATEGORIES as $key => $category) {
      $category_rows[] = [
        'permission' => $category['permission'],
        'category' => $category['label'],
        'description' => $category['description'],
      ];
    }

    $form['categories']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Permission'),
        $this->t('Category'),
        $this->t('Enables'),
      ],
      '#rows' => $category_rows,
    ];

    // Role Templates.
    $form['templates'] = [
      '#type' => 'details',
      '#title' => $this->t('Quick Setup: Role Templates'),
      '#open' => TRUE,
      '#description' => $this->t('Create pre-configured roles with one click. After creating a role, assign it to users who need MCP access.'),
    ];

    $existing_roles = $this->getExistingRoles();

    foreach (self::ROLE_TEMPLATES as $role_id => $template) {
      $role_exists = isset($existing_roles[$role_id]);
      $permission_list = array_map(
        fn($p) => str_replace('mcp_tools use ', '', $p),
        $template['permissions']
      );

      $form['templates'][$role_id] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['mcp-role-template'],
          'style' => 'border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; background: #f9f9f9;',
        ],
      ];

      $status_badge = $role_exists
        ? ' <span style="color: green; font-weight: normal;">&#10003; exists</span>'
        : '';

      $form['templates'][$role_id]['header'] = [
        '#type' => 'markup',
        '#markup' => '<h3 style="margin-top: 0;">' . $template['label'] . $status_badge . '</h3>',
      ];

      $form['templates'][$role_id]['description'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $template['description'] . '</p>',
      ];

      $form['templates'][$role_id]['permissions'] = [
        '#type' => 'markup',
        '#markup' => '<p><strong>' . $this->t('Includes:') . '</strong> ' .
          implode(', ', $permission_list) . '</p>',
      ];

      if (!$role_exists) {
        $form['templates'][$role_id]['create'] = [
          '#type' => 'submit',
          '#value' => $this->t('Create "@role" Role', ['@role' => $template['label']]),
          '#name' => 'create_role_' . $role_id,
          '#submit' => ['::createRoleSubmit'],
          '#ajax' => [
            'callback' => '::ajaxRefresh',
            'wrapper' => 'mcp-permissions-wrapper',
          ],
        ];
      }
      else {
        // Show which users have this role.
        $users_with_role = $this->getUsersWithRole($role_id);
        if (!empty($users_with_role)) {
          $form['templates'][$role_id]['users'] = [
            '#type' => 'markup',
            '#markup' => '<p><em>' . $this->t('Assigned to: @users', [
              '@users' => implode(', ', $users_with_role),
            ]) . '</em></p>',
          ];
        }
        else {
          $form['templates'][$role_id]['assign_link'] = [
            '#type' => 'markup',
            '#markup' => '<p><em>' . $this->t('Not assigned to any users. <a href=":url">Manage users</a>', [
              ':url' => Url::fromRoute('entity.user.collection')->toString(),
            ]) . '</em></p>',
          ];
        }
      }
    }

    // Current Roles with MCP Permissions.
    $form['current'] = [
      '#type' => 'details',
      '#title' => $this->t('Roles with MCP Permissions'),
      '#open' => TRUE,
    ];

    $roles_with_perms = $this->getRolesWithMcpPermissions();
    if (empty($roles_with_perms)) {
      $form['current']['empty'] = [
        '#type' => 'markup',
        '#markup' => '<p><em>' . $this->t('No roles have MCP permissions yet. Create a role above or grant permissions manually.') . '</em></p>',
      ];
    }
    else {
      $role_rows = [];
      foreach ($roles_with_perms as $role_id => $info) {
        $role_rows[] = [
          'role' => $info['label'],
          'permissions' => implode(', ', array_map(
            fn($p) => str_replace('mcp_tools use ', '', $p),
            $info['permissions']
          )),
          'users' => !empty($info['users']) ? implode(', ', $info['users']) : '-',
        ];
      }

      $form['current']['table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Role'),
          $this->t('MCP Permissions'),
          $this->t('Users'),
        ],
        '#rows' => $role_rows,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Main form doesn't do anything - role creation is handled by createRoleSubmit.
  }

  /**
   * AJAX callback to refresh the form.
   */
  public function ajaxRefresh(array &$form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * Submit handler for creating a role from a template.
   */
  public function createRoleSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'] ?? '';

    if (preg_match('/^create_role_(.+)$/', $button_name, $matches)) {
      $role_id = $matches[1];

      if (isset(self::ROLE_TEMPLATES[$role_id])) {
        $template = self::ROLE_TEMPLATES[$role_id];

        try {
          $role = $this->entityTypeManager->getStorage('user_role')->create([
            'id' => $role_id,
            'label' => $template['label'],
          ]);
          $role->save();

          foreach ($template['permissions'] as $permission) {
            $role->grantPermission($permission);
          }
          $role->save();

          $this->messenger()->addStatus($this->t('Created role "@role" with @count permissions.', [
            '@role' => $template['label'],
            '@count' => count($template['permissions']),
          ]));
        }
        catch (\Exception $e) {
          $this->messenger()->addError($this->t('Failed to create role: @message', [
            '@message' => $e->getMessage(),
          ]));
        }
      }
    }

    $form_state->setRebuild();
  }

  /**
   * Get existing roles keyed by ID.
   */
  private function getExistingRoles(): array {
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $result = [];
    foreach ($roles as $role) {
      $result[$role->id()] = $role->label();
    }
    return $result;
  }

  /**
   * Get users that have a specific role.
   */
  private function getUsersWithRole(string $role_id): array {
    $users = $this->entityTypeManager->getStorage('user')->loadByProperties([
      'roles' => $role_id,
    ]);
    return array_map(fn($user) => $user->getAccountName(), $users);
  }

  /**
   * Get all roles that have any MCP permissions.
   */
  private function getRolesWithMcpPermissions(): array {
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $result = [];

    foreach ($roles as $role) {
      $role_perms = $role->getPermissions();
      $mcp_perms = array_filter($role_perms, fn($p) => str_starts_with($p, 'mcp_tools '));

      if (!empty($mcp_perms)) {
        $users = $this->getUsersWithRole($role->id());
        $result[$role->id()] = [
          'label' => $role->label(),
          'permissions' => array_values($mcp_perms),
          'users' => $users,
        ];
      }
    }

    return $result;
  }

}
