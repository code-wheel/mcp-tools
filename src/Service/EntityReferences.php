<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint;

/**
 * Whether the reference fields of an entity point to something real.
 *
 * Our content tools save without Drupal's entity validation. So a tag
 * given by its name ("Technology") or by an id that is not there was
 * saved, the call said success, and the page showed no tag. This runs
 * Drupal's own reference rule (it exists, its bundle is allowed, it may
 * be referenced) and nothing else, so every other value is taken as
 * before.
 */
final class EntityReferences {

  /**
   * Where to look for an id, by the type a field refers to.
   */
  private const FINDERS = [
    'taxonomy_term' => 'mcp_tools_get_terms',
    'node' => 'mcp_tools_search_content',
    'user' => 'mcp_tools_get_users',
    'file' => 'mcp_tools_get_files',
  ];

  /**
   * Why a reference of the entity is not valid, or NULL when all are.
   *
   * Anything without fields (a stand-in in a unit test) has nothing to check.
   */
  public static function problem(object $entity): ?string {
    if (!$entity instanceof FieldableEntityInterface) {
      return NULL;
    }
    $problems = [];
    // A name where an id goes: Drupal's own check would look it up as an
    // id, and PostgreSQL refuses a text for a number with a database error.
    foreach ($entity->getFields() as $name => $items) {
      if (!in_array($items->getFieldDefinition()->getType(), ['entity_reference', 'image', 'file'], TRUE)) {
        continue;
      }
      foreach ($items->getValue() as $item) {
        $id = $item['target_id'] ?? NULL;
        if (is_string($id) && $id !== '' && !ctype_digit($id) && self::numericIds($items->getFieldDefinition())) {
          $problems[$name] = $name;
        }
      }
    }
    if ($problems !== []) {
      return self::describe($entity, $problems);
    }
    foreach ($entity->validate() as $violation) {
      if (!$violation->getConstraint() instanceof ValidReferenceConstraint) {
        continue;
      }
      $field = strtok((string) $violation->getPropertyPath(), '.');
      $problems[$field] = $field;
    }
    return $problems === [] ? NULL : self::describe($entity, $problems);
  }

  /**
   * Whether a reference field's target ids are numbers.
   *
   * The target_id property is an integer when the target type's ids are.
   */
  private static function numericIds(FieldDefinitionInterface $definition): bool {
    $property = $definition->getFieldStorageDefinition()->getPropertyDefinition('target_id');
    return $property === NULL || $property->getDataType() === 'integer';
  }

  /**
   * The answer for the fields whose references are not valid.
   */
  private static function describe(FieldableEntityInterface $entity, array $problems): string {
    $lines = [];
    foreach ($problems as $field) {
      $definition = $entity->getFieldDefinition($field);
      $target = (string) ($definition?->getSetting('target_type') ?? '');
      $given = array_map(static fn(array $item): string => (string) ($item['target_id'] ?? ''), $entity->get($field)->getValue());
      $line = "$field: no $target to refer to as " . implode(', ', $given) . '.';
      if (array_filter($given, static fn(string $id): bool => $id !== '' && !ctype_digit($id)) !== []) {
        $line .= ' Give its id, not its name.';
      }
      if (isset(self::FINDERS[$target])) {
        $line .= ' ' . self::FINDERS[$target] . ' finds the ids.';
      }
      $lines[] = $line;
    }
    return 'Nothing was saved. ' . implode(' ', $lines);
  }

}
