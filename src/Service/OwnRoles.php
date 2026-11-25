<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\RoleInterface;

/**
 * A connection does not give out more permissions than it has.
 *
 * The role tools refuse to give a permission for trusted roles only to a
 * role the user of the connection has. A role is the other way to the
 * same end: make a role with the permission, then take the role. Another
 * account is a third, with a password or an address the connection chose.
 * So the tools that assign roles ask here first. Drupal asks "administer
 * permissions" for giving roles; with it, a connection gives any role to
 * others, and still none above its own to itself, nor to an account it can
 * act as (ControlledAccounts).
 */
final class OwnRoles {

  /**
   * The roles that hold a permission the acting user lacks and may not give.
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
   *   Those of the roles that are above the actor. Empty when they add nothing
   *   the actor does not have, or are for another user and the actor has
   *   "administer permissions".
   */
  public static function raising(EntityTypeManagerInterface $entityTypeManager, ?AccountInterface $actor, int $uid, array $roleIds): array {
    if ($actor === NULL || !$entityTypeManager->hasDefinition('user_role')) {
      return [];
    }
    if ((int) $actor->id() !== $uid && !ControlledAccounts::has($uid) && $actor->hasPermission('administer permissions')) {
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

  /**
   * Why such a role is not given, for the answer.
   */
  public static function why(?AccountInterface $actor, int $uid): string {
    if ($actor !== NULL && (int) $actor->id() === $uid) {
      return 'a connection does not give its own user more permissions';
    }
    return ControlledAccounts::has($uid)
      ? 'a connection does not give an account it made, or whose address it set, more permissions than its own user has'
      : "a role with permissions the connection's own user lacks needs the permission \"administer permissions\"";
  }

}
