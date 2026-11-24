<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

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
   * The refusal when the current user may not set these fields, or NULL.
   *
   * Drupal's field access: on a node, the owner, the published status, the
   * dates, promote and sticky are for "administer nodes", as on the node
   * form. The tools checked the entity and took every field given.
   *
   * @param object $entity
   *   The entity, new or loaded.
   * @param string[] $fieldNames
   *   The fields the call sets.
   */
  public static function fieldsDenied(object $entity, array $fieldNames): ?array {
    // Anything without fields (a stand-in in a unit test) has none to check.
    if (!$entity instanceof FieldableEntityInterface || !self::applies()) {
      return NULL;
    }
    $denied = EntityValidation::notEditable($entity, array_values(array_unique($fieldNames)), \Drupal::currentUser());
    if ($denied === []) {
      return NULL;
    }
    return self::refusal(sprintf('This user may not set %s on %s.', implode(', ', $denied), $entity->getEntityTypeId()));
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
