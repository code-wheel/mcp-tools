<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AdminRoles;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\user\Entity\Role;
use Drupal\user\PermissionHandlerInterface;
use Drupal\user\RoleInterface;

/**
 * Service for managing roles and permissions.
 */
class RoleService {

  /**
   * Dangerous permissions that cannot be granted via MCP.
   *
   * SECURITY: This list prevents privilege escalation attacks.
   * Any permission that could allow bypassing MCP security controls
   * or granting elevated access should be included here.
   */
  protected const DANGEROUS_PERMISSIONS = [
    // Core administration permissions.
    'administer permissions',
    'administer users',
    'administer site configuration',
    'administer modules',
    'administer software updates',
    'administer themes',
    'administer menu',
    // Access bypass permissions.
    'bypass node access',
    'access all views',
    // Configuration management.
    'synchronize configuration',
    'import configuration',
    'export configuration',
    // User management.
    'cancel account',
    'select account cancellation method',
    // Translation (can modify any content).
    'translate interface',
    // URL/path manipulation.
    'administer url aliases',
    'create url aliases',
    // Block administration.
    'administer blocks',
    // Views administration.
    'administer views',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PermissionHandlerInterface $permissionHandler,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
    protected ?AccountInterface $currentUser = NULL,
  ) {}

  /**
   * List all roles.
   *
   * @return array
   *   Result with list of roles.
   */
  public function listRoles(): array {
    try {
      $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
      $result = [];

      foreach ($roles as $role) {
        // Count users with this role.
        $userCount = 0;
        // Anonymous and authenticated are special - don't count.
        if (!in_array($role->id(), ['anonymous', 'authenticated'])) {
          $userCount = $this->entityTypeManager->getStorage('user')
            ->getQuery()
            ->accessCheck(FALSE)
            ->condition('roles', $role->id())
            ->count()
            ->execute();
        }

        $permissions = $role->getPermissions();

        $result[] = [
          'id' => $role->id(),
          'label' => $role->label(),
          'weight' => (int) $role->getWeight(),
          'is_admin' => $role->isAdmin(),
          'permission_count' => count($permissions),
          'user_count' => (int) $userCount,
        ];
      }

      // Sort by weight.
      usort($result, fn($a, $b) => $a['weight'] <=> $b['weight']);

      return [
        'success' => TRUE,
        'data' => [
          'roles' => $result,
          'total' => count($result),
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to list roles: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Get role permissions.
   *
   * @param string $id
   *   Role machine name.
   *
   * @return array
   *   Result with role details and permissions.
   */
  public function getRolePermissions(string $id): array {
    try {
      $role = $this->entityTypeManager->getStorage('user_role')->load($id);

      if (!$role) {
        return [
          'success' => FALSE,
          'error' => "Role '$id' not found. Use mcp_structure_list_roles to see available roles.",
        ];
      }

      $rolePermissions = $role->getPermissions();
      $allPermissions = $this->permissionHandler->getPermissions();

      // Group permissions by provider.
      $permissionsByProvider = [];
      foreach ($rolePermissions as $permission) {
        if (isset($allPermissions[$permission])) {
          $provider = $allPermissions[$permission]['provider'] ?? 'unknown';
          $permissionsByProvider[$provider][] = [
            'id' => $permission,
            'title' => (string) ($allPermissions[$permission]['title'] ?? $permission),
            'description' => (string) ($allPermissions[$permission]['description'] ?? ''),
            'restrict_access' => !empty($allPermissions[$permission]['restrict access']),
          ];
        }
        else {
          // Permission exists on role but not in system (orphaned).
          $permissionsByProvider['_orphaned'][] = [
            'id' => $permission,
            'title' => $permission,
            'description' => 'Permission no longer exists in system',
            'restrict_access' => FALSE,
          ];
        }
      }

      // Sort providers and permissions.
      ksort($permissionsByProvider);
      foreach ($permissionsByProvider as &$perms) {
        usort($perms, fn($a, $b) => strcasecmp($a['title'], $b['title']));
      }

      return [
        'success' => TRUE,
        'data' => [
          'id' => $role->id(),
          'label' => $role->label(),
          'is_admin' => $role->isAdmin(),
          'permissions' => $rolePermissions,
          'permissions_by_provider' => $permissionsByProvider,
          'permission_count' => count($rolePermissions),
          'admin_path' => "/admin/people/roles/manage/$id",
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to get role permissions: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Create a new role.
   *
   * @param string $id
   *   Machine name.
   * @param string $label
   *   Human-readable name.
   * @param array $permissions
   *   Optional initial permissions to grant.
   *
   * @return array
   *   Result with success status.
   */
  public function createRole(string $id, string $label, array $permissions = []): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Validate machine name.
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $id)) {
      return [
        'success' => FALSE,
        'error' => 'Invalid machine name. Use lowercase letters, numbers, and underscores. Must start with a letter.',
      ];
    }

    if (strlen($id) > 32) {
      return [
        'success' => FALSE,
        'error' => 'Machine name must be 32 characters or less.',
      ];
    }

    // Check reserved role IDs.
    if (in_array($id, ['anonymous', 'authenticated', 'administrator'])) {
      return [
        'success' => FALSE,
        'error' => "Role ID '$id' is reserved.",
      ];
    }

    // Check if role exists.
    $existing = $this->entityTypeManager->getStorage('user_role')->load($id);
    if ($existing) {
      return [
        'success' => FALSE,
        'error' => "Role '$id' already exists.",
      ];
    }

    // Validate permissions.
    $validationResult = $this->validatePermissions($permissions);
    if (!$validationResult['valid']) {
      return [
        'success' => FALSE,
        'error' => $validationResult['error'],
      ];
    }

    try {
      $role = Role::create([
        'id' => $id,
        'label' => $label,
      ]);
      $role->save();

      // Grant initial permissions.
      $grantedPermissions = [];
      foreach ($permissions as $permission) {
        $role->grantPermission($permission);
        $grantedPermissions[] = $permission;
      }
      if (!empty($permissions)) {
        $role->save();
      }

      [$trail, $note] = $this->restrictedTrail($validationResult['restricted']);
      // With a permission for trusted roles only the line is one a person
      // should look at.
      $log = $trail === [] ? 'logSuccess' : 'logSensitive';
      $this->auditLogger->$log('create_role', 'user_role', $id, [
        'label' => $label,
        'permissions' => $grantedPermissions,
      ] + $trail);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'label' => $label,
          'permissions_granted' => $grantedPermissions,
          'message' => "Role '$label' ($id) created successfully." . $note,
          'admin_path' => "/admin/people/roles/manage/$id",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_role', 'user_role', $id, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to create role: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Delete a role.
   *
   * @param string $id
   *   Role machine name.
   *
   * @return array
   *   Result with success status.
   */
  public function deleteRole(string $id): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Prevent deletion of core roles.
    if (in_array($id, ['anonymous', 'authenticated'])) {
      return [
        'success' => FALSE,
        'error' => "Cannot delete core role '$id'.",
      ];
    }

    // And of an administrator role, by its flag or by its name: with it
    // go the rights of every administrator but user 1.
    if (AdminRoles::isAdmin($this->entityTypeManager, $id)) {
      return [
        'success' => FALSE,
        'error' => "Cannot delete the administrator role '$id' via MCP.",
      ];
    }

    $role = $this->entityTypeManager->getStorage('user_role')->load($id);

    if (!$role) {
      return [
        'success' => FALSE,
        'error' => "Role '$id' not found. Use mcp_structure_list_roles to see available roles.",
      ];
    }

    // Count users with this role.
    // SECURITY NOTE: accessCheck(FALSE) is intentional here.
    // This is a system-level count query to inform about role usage.
    // We need to count ALL users with this role, not just those the
    // current user can view, to provide accurate usage information.
    $userCount = $this->entityTypeManager->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('roles', $id)
      ->count()
      ->execute();

    try {
      $label = $role->label();
      $role->delete();

      $this->auditLogger->logSuccess('delete_role', 'user_role', $id, [
        'label' => $label,
        'affected_users' => (int) $userCount,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'message' => "Role '$label' ($id) deleted successfully.",
          'affected_users' => (int) $userCount,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_role', 'user_role', $id, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to delete role: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Grant permissions to a role.
   *
   * @param string $roleId
   *   Role machine name.
   * @param array $permissions
   *   Permissions to grant.
   *
   * @return array
   *   Result with success status.
   */
  public function grantPermissions(string $roleId, array $permissions): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $role = $this->entityTypeManager->getStorage('user_role')->load($roleId);

    if (!$role) {
      return [
        'success' => FALSE,
        'error' => "Role '$roleId' not found. Use mcp_structure_list_roles to see available roles.",
      ];
    }

    // Validate permissions.
    $validationResult = $this->validatePermissions($permissions, $roleId);
    if (!$validationResult['valid']) {
      return [
        'success' => FALSE,
        'error' => $validationResult['error'],
      ];
    }

    try {
      $granted = [];
      $alreadyHad = [];

      foreach ($permissions as $permission) {
        if ($role->hasPermission($permission)) {
          $alreadyHad[] = $permission;
        }
        else {
          $role->grantPermission($permission);
          $granted[] = $permission;
        }
      }

      if (!empty($granted)) {
        $role->save();
      }

      [$trail, $note] = $this->restrictedTrail(array_values(array_intersect($granted, $validationResult['restricted'])));
      $log = $trail === [] ? 'logSuccess' : 'logSensitive';
      $this->auditLogger->$log('grant_permissions', 'user_role', $roleId, [
        'granted' => $granted,
        'already_had' => $alreadyHad,
      ] + $trail);

      return [
        'success' => TRUE,
        'data' => [
          'role' => $roleId,
          'granted' => $granted,
          'already_had' => $alreadyHad,
          'message' => count($granted) . ' permission(s) granted to ' . $role->label() . '.' . $note,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('grant_permissions', 'user_role', $roleId, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to grant permissions: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Revoke permissions from a role.
   *
   * @param string $roleId
   *   Role machine name.
   * @param array $permissions
   *   Permissions to revoke.
   *
   * @return array
   *   Result with success status.
   */
  public function revokePermissions(string $roleId, array $permissions): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $role = $this->entityTypeManager->getStorage('user_role')->load($roleId);

    if (!$role) {
      return [
        'success' => FALSE,
        'error' => "Role '$roleId' not found. Use mcp_structure_list_roles to see available roles.",
      ];
    }

    try {
      $revoked = [];
      $didntHave = [];

      foreach ($permissions as $permission) {
        if ($role->hasPermission($permission)) {
          $role->revokePermission($permission);
          $revoked[] = $permission;
        }
        else {
          $didntHave[] = $permission;
        }
      }

      if (!empty($revoked)) {
        $role->save();
      }

      $this->auditLogger->logSuccess('revoke_permissions', 'user_role', $roleId, [
        'revoked' => $revoked,
        'didnt_have' => $didntHave,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'role' => $roleId,
          'revoked' => $revoked,
          'didnt_have' => $didntHave,
          'message' => count($revoked) . ' permission(s) revoked from ' . $role->label() . '.',
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('revoke_permissions', 'user_role', $roleId, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to revoke permissions: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Validate permissions.
   *
   * @param array $permissions
   *   Permissions to validate.
   * @param string|null $roleId
   *   The role that would get them, when it is there already.
   *
   * @return array
   *   ['valid' => bool, 'error' => string|null, 'restricted' => string[]].
   *   'restricted' names the permissions for trusted roles only that the
   *   admin scope lets through, for the audit log.
   */
  protected function validatePermissions(array $permissions, ?string $roleId = NULL): array {
    $allPermissions = $this->permissionHandler->getPermissions();
    $invalid = [];
    $dangerous = [];
    $needAdminScope = [];
    $forEveryone = [];
    $raisesTheCaller = [];
    $restricted = [];

    foreach ($permissions as $permission) {
      if (!isset($allPermissions[$permission])) {
        $invalid[] = $permission;
      }
      elseif (in_array($permission, self::DANGEROUS_PERMISSIONS)) {
        $dangerous[] = $permission;
      }
      // MCP Tools' own permissions say which tools a user may call. A
      // session must not widen that for its own role, or for any other.
      elseif (str_starts_with((string) ($allPermissions[$permission]['provider'] ?? ''), 'mcp_tools')) {
        $dangerous[] = $permission;
      }
      // What Drupal itself marks "give to trusted roles only", of every
      // module, and not on the list above. The admin scope is the gate.
      elseif (!empty($allPermissions[$permission]['restrict access'])) {
        if (!$this->accessManager->hasScope(AccessManager::SCOPE_ADMIN)) {
          $needAdminScope[] = $permission;
        }
        // Not for the two roles that are every visitor and every logged-in
        // user.
        elseif (in_array($roleId, [RoleInterface::ANONYMOUS_ID, RoleInterface::AUTHENTICATED_ID], TRUE)) {
          $forEveryone[] = $permission;
        }
        // And a connection does not raise itself: not for a role its own
        // user has, unless that user has the permission already.
        elseif ($roleId !== NULL && $this->currentUser !== NULL
          && in_array($roleId, $this->currentUser->getRoles(), TRUE)
          && !$this->currentUser->hasPermission($permission)) {
          $raisesTheCaller[] = $permission;
        }
        else {
          $restricted[] = $permission;
        }
      }
    }

    if (!empty($invalid)) {
      return [
        'valid' => FALSE,
        'error' => 'Invalid permissions: ' . implode(', ', $invalid),
      ];
    }

    $refused = [];
    if (!empty($dangerous)) {
      $refused[] = 'Cannot grant dangerous permissions via MCP: ' . implode(', ', $dangerous) . '. A person can grant them on the permissions page.';
    }
    if (!empty($needAdminScope)) {
      $refused[] = 'Not granted, these are for trusted roles only and need the admin scope: ' . implode(', ', $needAdminScope) . '.';
    }
    if (!empty($forEveryone)) {
      $refused[] = 'Not granted to the role "' . $roleId . '", which is every visitor or every logged-in user. These are for trusted roles only: ' . implode(', ', $forEveryone) . '.';
    }
    if (!empty($raisesTheCaller)) {
      $refused[] = 'Not granted to the role "' . $roleId . '": the user this connection runs as has that role, and a connection does not raise itself. These are for trusted roles only: ' . implode(', ', $raisesTheCaller) . '. A person can grant them on the permissions page.';
    }
    if (!empty($refused)) {
      return ['valid' => FALSE, 'error' => implode(' ', $refused)];
    }

    return ['valid' => TRUE, 'error' => NULL, 'restricted' => $restricted];
  }

  /**
   * The permissions of a call that need a yes from a person first.
   *
   * Those are the permissions for trusted roles only that the call would
   * grant. A call that is refused anyway has none: nobody is asked to
   * confirm what does not happen.
   *
   * @param array $permissions
   *   The permissions the call names.
   * @param string $roleId
   *   The role that would get them.
   * @param bool $newRole
   *   Whether the call makes the role (TRUE) or adds to one (FALSE).
   *
   * @return string[]
   *   Their names.
   */
  public function restrictedToGrant(array $permissions, string $roleId, bool $newRole): array {
    $exists = $this->entityTypeManager->getStorage('user_role')->load($roleId) !== NULL;
    if ($exists === $newRole) {
      return [];
    }
    $validationResult = $this->validatePermissions($permissions, $newRole ? NULL : $roleId);
    return $validationResult['valid'] ? $validationResult['restricted'] : [];
  }

  /**
   * What a grant of permissions for trusted roles only adds to the trail.
   *
   * @param string[] $restricted
   *   Those of the granted permissions that are for trusted roles only.
   *
   * @return array
   *   The detail for the audit line, and the sentence for the answer.
   */
  protected function restrictedTrail(array $restricted): array {
    if ($restricted === []) {
      return [[], ''];
    }
    return [
      ['restrict_access' => $restricted],
      ' Drupal marks these for trusted roles only: ' . implode(', ', $restricted) . '. The audit log names them.',
    ];
  }

}
