<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Entity\Plugin\Validation\Constraint\EntityChangedConstraint;

/**
 * Drupal's validation of every entity a tool saves.
 *
 * The tools saved entities and config without it: an email "nope", a
 * reference to nothing, an image effect with no width were stored and the
 * call said success. A tool call runs this before each save (from
 * hook_entity_presave), so one rule covers every tool and every entity a
 * tool saves on the way. Only what the save adds is refused: a value that
 * was wrong before does not stop an unrelated change. Saves outside a tool
 * call are not looked at.
 */
final class SaveGuard {

  public function __construct(
    private readonly McpToolCallContext $toolCallContext,
  ) {}

  /**
   * Refuses the save when it adds a violation.
   *
   * @throws \Drupal\mcp_tools\Service\SaveRefusedException
   *   With what is wrong, field by field.
   */
  public function check(EntityInterface $entity): void {
    if (!$this->toolCallContext->isActive()) {
      return;
    }
    // Before validate(): on PostgreSQL its lookup of a name as an id is a
    // database error, which would let the save go on into the same error.
    if ($entity instanceof FieldableEntityInterface && ($names = EntityReferences::nameForId($entity)) !== NULL) {
      throw new SaveRefusedException($names);
    }
    try {
      $now = self::violations($entity);
    }
    catch (\Throwable $e) {
      // Where Drupal cannot validate (a config entity half built), the save
      // goes on as it would without this.
      return;
    }
    if ($now === []) {
      return;
    }
    $original = NULL;
    if (!$entity->isNew()) {
      // getOriginal() came with Drupal 11.2; before, the "original" property.
      $original = method_exists($entity, 'getOriginal') ? $entity->getOriginal() : ($entity->original ?? NULL);
    }
    try {
      $new = $original === NULL ? $now : array_diff($now, self::violations($original));
    }
    catch (\Throwable $e) {
      $new = $now;
    }
    if ($new === []) {
      return;
    }
    throw new SaveRefusedException(sprintf('Nothing was saved: the %s does not pass Drupal\'s validation. %s', $entity->getEntityTypeId(), implode(' ', array_unique($new))));
  }

  /**
   * The violations of an entity, as "path: message" lines.
   *
   * @return string[]
   *   One line a violation.
   */
  private static function violations(EntityInterface $entity): array {
    if ($entity instanceof FieldableEntityInterface) {
      $list = $entity->validate();
    }
    elseif ($entity instanceof FieldStorageDefinitionInterface || $entity instanceof FieldDefinitionInterface) {
      // Field storage and field config check themselves on save, and their
      // typed data validation leaves a reference in the field definitions
      // that Drupal then cannot cache ("Serialization of WeakReference").
      return [];
    }
    elseif ($entity instanceof ConfigEntityInterface) {
      $list = $entity->getTypedData()->validate();
    }
    else {
      return [];
    }
    $lines = [];
    foreach ($list as $violation) {
      // "Modified by another user" guards a form someone has open. A tool
      // has none, and a long STDIO session keeps the request time it started
      // with: every update after a create would look stale.
      if ($violation->getConstraint() instanceof EntityChangedConstraint) {
        continue;
      }
      $path = (string) $violation->getPropertyPath();
      $lines[] = ($path !== '' ? $path . ': ' : '') . strip_tags((string) $violation->getMessage());
    }
    return $lines;
  }

}
