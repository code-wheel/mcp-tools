<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityInterface;

/**
 * Drupal's own access check for an entity a tool acts on.
 *
 * The content, media, scheduler, metatag, clone, moderation, user and
 * batch tools checked the connection's scope and the tool's permission,
 * not the entity: a user who may not edit a node edited it through a
 * tool. Now a tool may do to an entity what its user may do in Drupal, on a
 * site with access.entity_access (new sites; a site that updates keeps the
 * old way until someone ticks it).
 */
final class EntityAccess {

  /**
   * The refusal when the current user may not do $operation, or NULL.
   */
  public static function denied(EntityInterface $entity, string $operation): ?array {
    if (!self::applies() || $entity->access($operation)) {
      return NULL;
    }
    return self::refusal(sprintf('This user may not %s %s %s.', $operation, $entity->getEntityTypeId(), $entity->id()));
  }

  /**
   * The refusal when the current user may not create one, or NULL.
   */
  public static function createDenied(string $entityTypeId, ?string $bundle = NULL): ?array {
    if (!self::applies()) {
      return NULL;
    }
    $handler = \Drupal::entityTypeManager()->getAccessControlHandler($entityTypeId);
    if ($handler->createAccess($bundle)) {
      return NULL;
    }
    return self::refusal(sprintf('This user may not create %s%s.', $entityTypeId, $bundle !== NULL ? " of type $bundle" : ''));
  }

  /**
   * Whether the site has tools follow entity access.
   */
  public static function applies(): bool {
    // No container is no running site (a unit test): nothing to ask.
    return \Drupal::hasContainer() && (bool) \Drupal::config('mcp_tools.settings')->get('access.entity_access');
  }

  /**
   * The answer of a refusal.
   */
  private static function refusal(string $message): array {
    return [
      'success' => FALSE,
      'error' => $message . ' Drupal decides this by the user\'s permissions, as on the site itself. No argument changes that.',
      'code' => 'ACCESS_DENIED',
    ];
  }

}
