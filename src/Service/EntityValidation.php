<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Drupal's validation of an entity, as JSON:API runs it before a save.
 *
 * Only the fields a call gives are held to it, so a value that was wrong
 * before the call does not stop an unrelated change.
 */
final class EntityValidation {

  /**
   * The fields a user may not set, of those given.
   *
   * @return string[]
   *   The field names, in the order given.
   */
  public static function notEditable(FieldableEntityInterface $entity, array $fieldNames, AccountInterface $account): array {
    $denied = [];
    foreach ($fieldNames as $name) {
      if ($entity->hasField($name) && !$entity->get($name)->access('edit', $account)) {
        $denied[] = $name;
      }
    }
    return $denied;
  }

  /**
   * What is wrong with the given fields, or NULL when nothing is.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity, with the call's values set.
   * @param string[]|null $fieldNames
   *   The fields the call set, or NULL for all (a new entity).
   */
  public static function problem(FieldableEntityInterface $entity, ?array $fieldNames = NULL): ?string {
    if (($names = EntityReferences::nameForId($entity, $fieldNames)) !== NULL) {
      return $names;
    }
    $violations = $entity->validate();
    if ($fieldNames !== NULL) {
      $violations->filterByFields(array_diff(array_keys($entity->getFieldDefinitions()), $fieldNames));
    }
    if ($violations->count() === 0) {
      return NULL;
    }
    $lines = [];
    foreach ($violations as $violation) {
      $path = (string) $violation->getPropertyPath();
      $lines[] = ($path !== '' ? strtok($path, '.') . ': ' : '') . strip_tags((string) $violation->getMessage());
    }
    return 'Nothing was saved. ' . implode(' ', array_unique($lines));
  }

}
