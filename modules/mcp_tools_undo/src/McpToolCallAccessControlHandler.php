<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_undo;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Seeing a tool call and undoing it, each by its own permission.
 *
 * Nobody makes or edits one by hand: only tool calls write them.
 */
class McpToolCallAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermissions($account, ['view mcp tool calls', 'undo mcp tool calls'], 'OR'),
      'undo' => AccessResult::allowedIfHasPermission($account, 'undo mcp tool calls')
        ->andIf(AccessResult::allowedIf($entity->get('state')->value === 'applied'))
        ->addCacheableDependency($entity),
      default => AccessResult::forbidden(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return AccessResult::forbidden();
  }

}
