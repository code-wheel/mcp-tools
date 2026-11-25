<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

/**
 * The accounts a connection can act as.
 *
 * A connection made the account, so it knows the password or chose the
 * address, or it changed the address. With either it can log in there. Such
 * an account gets no more permissions than the connection's own user has,
 * as that user itself does not. Accounts made before this list began are
 * not on it.
 */
final class ControlledAccounts {

  /**
   * The key-value collection: user ID to TRUE.
   */
  private const COLLECTION = 'mcp_tools.controlled_accounts';

  /**
   * Notes that a connection can act as this account.
   */
  public static function add(int $uid): void {
    if (\Drupal::hasContainer() && \Drupal::hasService('keyvalue')) {
      \Drupal::keyValue(self::COLLECTION)->set((string) $uid, TRUE);
    }
  }

  /**
   * Whether a connection can act as this account.
   */
  public static function has(int $uid): bool {
    return in_array($uid, self::all(), TRUE);
  }

  /**
   * All such accounts.
   *
   * @return int[]
   *   Their user IDs.
   */
  public static function all(): array {
    // No container is no running site (a unit test): there are none.
    if (!\Drupal::hasContainer() || !\Drupal::hasService('keyvalue')) {
      return [];
    }
    return array_map('intval', array_keys(\Drupal::keyValue(self::COLLECTION)->getAll()));
  }

}
