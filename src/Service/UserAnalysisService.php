<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\PermissionHandlerInterface;

/**
 * Service for analyzing users and permissions.
 */
class UserAnalysisService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PermissionHandlerInterface $permissionHandler,
  ) {}

  /**
   * Get all roles with their permissions.
   *
   * @return array
   *   Roles and permissions data.
   */
  public function getRoles(): array {
    $roleStorage = $this->entityTypeManager->getStorage('user_role');
    $roles = $roleStorage->loadMultiple();

    $result = [];
    foreach ($roles as $role) {
      $permissions = $role->getPermissions();
      $result[] = [
        'id' => $role->id(),
        'label' => $role->label(),
        'weight' => $role->getWeight(),
        'is_admin' => $role->isAdmin(),
        'permission_count' => count($permissions),
        'permissions' => $permissions,
      ];
    }

    // Sort by weight.
    usort($result, fn($a, $b) => $a['weight'] - $b['weight']);

    return [
      'total_roles' => count($result),
      'roles' => $result,
    ];
  }

  /**
   * Get all available permissions.
   *
   * @return array
   *   All permissions grouped by provider.
   */
  public function getPermissions(): array {
    $permissions = $this->permissionHandler->getPermissions();

    $grouped = [];
    foreach ($permissions as $name => $permission) {
      $provider = $permission['provider'] ?? 'unknown';
      if (!isset($grouped[$provider])) {
        $grouped[$provider] = [];
      }
      $grouped[$provider][] = [
        'name' => $name,
        'title' => (string) ($permission['title'] ?? $name),
        'description' => isset($permission['description']) ? (string) $permission['description'] : NULL,
        'restrict_access' => $permission['restrict access'] ?? FALSE,
      ];
    }

    ksort($grouped);

    return [
      'total_permissions' => count($permissions),
      'providers' => count($grouped),
      'by_provider' => $grouped,
    ];
  }

  /**
   * Get users summary.
   *
   * @param int $limit
   *   Maximum users to return.
   * @param string|null $role
   *   Filter by role.
   *
   * @return array
   *   Users summary.
   */
  public function getUsers(int $limit = 50, ?string $role = NULL): array {
    $userStorage = $this->entityTypeManager->getStorage('user');

    // Get counts.
    $totalQuery = $userStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('uid', 0, '>');
    $total = $totalQuery->count()->execute();

    $activeQuery = $userStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('uid', 0, '>')
      ->condition('status', 1);
    $activeCount = $activeQuery->count()->execute();

    // Get user list.
    $query = $userStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('uid', 0, '>')
      ->sort('created', 'DESC')
      ->range(0, $limit);

    if ($role) {
      $query->condition('roles', $role);
    }

    $uids = $query->execute();
    $users = $userStorage->loadMultiple($uids);

    $userList = [];
    foreach ($users as $user) {
      $userList[] = [
        'uid' => $user->id(),
        'name' => $user->getAccountName(),
        'email' => $user->getEmail(),
        'status' => $user->isActive() ? 'active' : 'blocked',
        'roles' => $user->getRoles(TRUE), // Exclude 'authenticated'.
        'created' => date('Y-m-d H:i:s', $user->getCreatedTime()),
        'last_access' => $user->getLastAccessedTime()
          ? date('Y-m-d H:i:s', $user->getLastAccessedTime())
          : 'Never',
      ];
    }

    return [
      'total_users' => (int) $total,
      'active_users' => (int) $activeCount,
      'blocked_users' => (int) $total - (int) $activeCount,
      'returned' => count($userList),
      'users' => $userList,
    ];
  }

  /**
   * Analyze permission usage.
   *
   * @param string $permission
   *   Permission to check.
   *
   * @return array
   *   Which roles have this permission.
   */
  public function analyzePermission(string $permission): array {
    $roleStorage = $this->entityTypeManager->getStorage('user_role');
    $roles = $roleStorage->loadMultiple();

    $hasPermission = [];
    foreach ($roles as $role) {
      if ($role->hasPermission($permission) || $role->isAdmin()) {
        $hasPermission[] = [
          'id' => $role->id(),
          'label' => $role->label(),
          'is_admin' => $role->isAdmin(),
          'granted_explicitly' => $role->hasPermission($permission),
        ];
      }
    }

    return [
      'permission' => $permission,
      'roles_with_permission' => count($hasPermission),
      'roles' => $hasPermission,
    ];
  }

}
