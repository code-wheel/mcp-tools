<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\user\Entity\Role;
use Drupal\user\PermissionHandlerInterface;

/**
 * Service for managing roles and permissions.
 */
class RoleService {

  /**
   * Dangerous permissions that cannot be granted via MCP.
   */
  protected const DANGEROUS_PERMISSIONS = [
    'administer permissions',
    'administer users',
    'administer site configuration',
    'administer modules',
    'administer software updates',
    'administer themes',
    'bypass node access',
    'synchronize configuration',
    'import configuration',
    'export configuration',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PermissionHandlerInterface $permissionHandler,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
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

      $this->auditLogger->logSuccess('create_role', 'user_role', $id, [
        'label' => $label,
        'permissions' => $grantedPermissions,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'label' => $label,
          'permissions_granted' => $grantedPermissions,
          'message' => "Role '$label' ($id) created successfully.",
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
    $validationResult = $this->validatePermissions($permissions);
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

      $this->auditLogger->logSuccess('grant_permissions', 'user_role', $roleId, [
        'granted' => $granted,
        'already_had' => $alreadyHad,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'role' => $roleId,
          'granted' => $granted,
          'already_had' => $alreadyHad,
          'message' => count($granted) . ' permission(s) granted to ' . $role->label() . '.',
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
   *
   * @return array
   *   ['valid' => bool, 'error' => string|null].
   */
  protected function validatePermissions(array $permissions): array {
    $allPermissions = $this->permissionHandler->getPermissions();
    $invalid = [];
    $dangerous = [];

    foreach ($permissions as $permission) {
      if (!isset($allPermissions[$permission])) {
        $invalid[] = $permission;
      }
      elseif (in_array($permission, self::DANGEROUS_PERMISSIONS)) {
        $dangerous[] = $permission;
      }
    }

    if (!empty($invalid)) {
      return [
        'valid' => FALSE,
        'error' => 'Invalid permissions: ' . implode(', ', $invalid),
      ];
    }

    if (!empty($dangerous)) {
      return [
        'valid' => FALSE,
        'error' => 'Cannot grant dangerous permissions via MCP: ' . implode(', ', $dangerous),
      ];
    }

    return ['valid' => TRUE, 'error' => NULL];
  }

}
