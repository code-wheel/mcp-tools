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
   * Gets the site's permissions by module, all or a slice.
   *
   * Each says what it is once, in plain text: a title without its markup
   * and only when it is more than the name, a description only when there
   * is one, restrict_access only when set.
   *
   * @param string|null $search
   *   Only permissions with this in their name or title (any case).
   * @param string|null $provider
   *   Only the permissions of this module.
   *
   * @return array
   *   Permissions grouped by provider, or an error for an unknown module.
   */
  public function getPermissions(?string $search = NULL, ?string $provider = NULL): array {
    $permissions = $this->permissionHandler->getPermissions();
    $plain = static fn(mixed $text): string => trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5));

    $providers = array_unique(array_map(static fn(array $permission): string => (string) ($permission['provider'] ?? 'unknown'), $permissions));
    sort($providers);
    $provider = $provider === NULL || trim($provider) === '' ? NULL : trim($provider);
    if ($provider !== NULL && !in_array($provider, $providers, TRUE)) {
      return ['error' => "No module named '$provider' gives permissions. Modules that do: " . implode(', ', $providers) . '.'];
    }
    $search = $search === NULL || trim($search) === '' ? NULL : mb_strtolower(trim($search));

    $grouped = [];
    $matching = 0;
    foreach ($permissions as $name => $permission) {
      $from = (string) ($permission['provider'] ?? 'unknown');
      $title = $plain($permission['title'] ?? $name);
      if (($provider !== NULL && $from !== $provider)
        || ($search !== NULL && !str_contains(mb_strtolower($name . ' ' . $title), $search))) {
        continue;
      }
      $item = ['name' => (string) $name];
      if (mb_strtolower($title) !== mb_strtolower((string) $name)) {
        $item['title'] = $title;
      }
      $description = $plain($permission['description'] ?? '');
      if ($description !== '') {
        $item['description'] = $description;
      }
      if (!empty($permission['restrict access'])) {
        $item['restrict_access'] = TRUE;
      }
      $grouped[$from][] = $item;
      $matching++;
    }
    ksort($grouped);

    $result = [
      'total_permissions' => count($permissions),
      'providers' => count($grouped),
      'by_provider' => $grouped,
    ];
    if ($search !== NULL || $provider !== NULL) {
      $result['matching'] = $matching;
    }
    return $result;
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
      // Exclude 'authenticated'.
        'roles' => $user->getRoles(TRUE),
        'created' => date('Y-m-d H:i:s', (int) $user->getCreatedTime()),
        'last_access' => $user->getLastAccessedTime()
          ? date('Y-m-d H:i:s', (int) $user->getLastAccessedTime())
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
