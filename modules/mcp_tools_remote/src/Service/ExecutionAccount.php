<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Service;

use Drupal\user\UserInterface;

/**
 * Whether setup may make an account the execution user.
 *
 * The names setup uses are known, and on a site with open registration
 * anyone can have registered one. An account that is there already is taken
 * only when a person gave it the role.
 */
final class ExecutionAccount {

  /**
   * Why an account is not taken, or NULL when it may be.
   */
  public static function refusal(?UserInterface $account, string $roleId): ?string {
    if ($account === NULL || $account->hasRole($roleId)) {
      return NULL;
    }
    return "An account named '" . $account->getAccountName() . "' (uid " . $account->id() . ") is there already and does not have the role '$roleId'. It can be anyone's: look whose it is. If it is the right one, give it the role and run this again, or use another name.";
  }

}
