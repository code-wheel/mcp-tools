<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_translate\Service;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools\Service\EntityAccess;
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
    protected ?ContentTranslationManagerInterface $translationManager = NULL,
  ) {}

  /**
   * Says no for a node whose content type is not enabled for translation.
   *
   * The API takes a translation of any node. A site whose owner has not
   * switched translation on for a content type has no page to see or edit
   * such a translation, so the tools do not make one.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to be translated.
   *
   * @return array<string, mixed>|null
   *   A legacy MCP error result, or NULL when the type is enabled.
   */
  public function checkTranslatable(NodeInterface $node): ?array {
    if ($this->translationManager === NULL || $this->translationManager->isEnabled('node', $node->bundle())) {
      return NULL;
    }
    return [
      'success' => FALSE,
      'error' => sprintf(
        "The content type '%s' is not enabled for translation, so node %d was not translated. A person enables it under Configuration, Content language and translation (/admin/config/regional/content-language).",
        $node->bundle(),
        $node->id(),
      ),
    ];
  }

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
   * @param string|null $operation
   *   'create' or 'update' for a write, which Drupal's translation handler
   *   decides where tools follow entity access; NULL for a read.
   *
   * @return array<string, mixed>|null
   *   A legacy MCP error result when access is denied, NULL when allowed.
   */
  public function checkTranslateAccess(NodeInterface $node, ?string $operation = NULL): ?array {
    $entityTypeId = $node->getEntityTypeId();
    // Where tools follow entity access, Drupal's translation handler decides
    // a write, as its translation forms do: it also asks "create content
    // translations" or "update content translations", which this did not.
    if ($operation !== NULL && EntityAccess::applies() && $this->entityTypeManager->hasHandler($entityTypeId, 'translation')) {
      $handler = $this->entityTypeManager->getHandler($entityTypeId, 'translation');
      if (method_exists($handler, 'getTranslationAccess') && !$handler->getTranslationAccess($node, $operation)->isAllowed()) {
        return [
          'success' => FALSE,
          'error' => sprintf("Access denied: this user may not %s translations of '%s' content (Drupal asks for \"%s content translations\" and a translate permission).", $operation, $node->bundle(), $operation),
          'code' => 'ACCESS_DENIED',
        ];
      }
    }
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
