<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_translate\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\node\NodeInterface;

/**
 * Loads nodes and authorizes MCP translation operations.
 *
 * Separated from ContentTranslationService so the translation mechanics
 * (field copying, paragraph duplication) stay independent of the access
 * concern. The MCP translation tools otherwise rely only on the coarse
 * write scope; this enforces Drupal's bundle-granular content translation
 * permission — the permission this site uses to gate editorial translation —
 * so the MCP service account can only translate node types it is explicitly
 * granted (e.g. "translate article node").
 */
class ContentTranslationAccess {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Loads a node by ID, bypassing the entity cache.
   *
   * @param int $nid
   *   The node ID.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The node, or NULL if it does not exist.
   */
  public function loadNode(int $nid): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache([$nid]);
    $node = $storage->load($nid);
    return $node instanceof NodeInterface ? $node : NULL;
  }

  /**
   * Checks whether the current user may translate the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to be translated.
   *
   * @return array<string, mixed>|null
   *   A legacy MCP error result when access is denied, NULL when allowed.
   */
  public function checkTranslateAccess(NodeInterface $node): ?array {
    $entityTypeId = $node->getEntityTypeId();
    $permission = $node->getEntityType()->getPermissionGranularity() === 'bundle'
      ? "translate {$node->bundle()} {$entityTypeId}"
      : "translate {$entityTypeId}";

    if ($this->currentUser->hasPermission('translate any entity')
      || $this->currentUser->hasPermission($permission)) {
      return NULL;
    }

    $this->auditLogger->logFailure('translate_access_denied', 'node', (string) $node->id(), [
      'bundle' => $node->bundle(),
      'required_permission' => $permission,
    ]);

    return [
      'success' => FALSE,
      'error' => sprintf(
        "Access denied: translating a '%s' node requires the '%s' permission.",
        $node->bundle(),
        $permission,
      ),
    ];
  }

}
