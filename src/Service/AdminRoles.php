<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\RoleInterface;

/**
 * Says which roles are administrator roles.
 *
 * A role is an administrator role by what it is: Drupal's own flag, which
 * gives it every permission. The name is checked as well, as it always was,
 * so a role called "admin" stays out of reach while its flag is off. The
 * tools went by the name alone. A site whose administrator role is called
 * "site_owner" had none of the protection.
 */
final class AdminRoles {

  /**
   * Role names that are refused whatever the role is.
   */
  private const NAMES = ['administrator', 'admin'];

  /**
   * Patterns of role names that are refused: admin..., super..., and so on.
   */
  private const PATTERNS = ['/^admin/i', '/administrator/i', '/^super/i'];

  /**
   * Whether a role is an administrator role, by its flag or by its name.
   */
  public static function isAdmin(EntityTypeManagerInterface $entityTypeManager, string $roleId): bool {
    if (in_array(strtolower(trim($roleId)), self::NAMES, TRUE)) {
      return TRUE;
    }
    foreach (self::PATTERNS as $pattern) {
      if (preg_match($pattern, $roleId)) {
        return TRUE;
      }
    }
    if (!$entityTypeManager->hasDefinition('user_role')) {
      return FALSE;
    }
    $role = $entityTypeManager->getStorage('user_role')->load($roleId);
    return $role instanceof RoleInterface && $role->isAdmin();
  }

}
