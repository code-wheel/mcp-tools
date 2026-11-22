<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\RoleInterface;

/**
 * A connection does not give its own user more permissions.
 *
 * The role tools refuse to give a permission for trusted roles only to a
 * role the user of the connection has. A role is the other way to the
 * same end: make a role with the permission, then take the role. So the
 * tools that assign roles ask here first.
 */
final class OwnRoles {

  /**
   * The roles that would give the acting user a permission it lacks.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface|null $actor
   *   The user the connection runs as.
   * @param int $uid
   *   The user who would get the roles.
   * @param string[] $roleIds
   *   The roles.
   *
   * @return string[]
   *   Those of the roles that raise the actor. Empty when the roles are for
   *   another user, or add nothing the actor does not have already.
   */
  public static function raising(EntityTypeManagerInterface $entityTypeManager, ?AccountInterface $actor, int $uid, array $roleIds): array {
    if ($actor === NULL || (int) $actor->id() !== $uid || !$entityTypeManager->hasDefinition('user_role')) {
      return [];
    }
    $raising = [];
    $storage = $entityTypeManager->getStorage('user_role');
    foreach ($roleIds as $roleId) {
      $role = $storage->load($roleId);
      if (!$role instanceof RoleInterface) {
        continue;
      }
      foreach ($role->getPermissions() as $permission) {
        if (!$actor->hasPermission($permission)) {
          $raising[] = $roleId;
          break;
        }
      }
    }
    return $raising;
  }

}
