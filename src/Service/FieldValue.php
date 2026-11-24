<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * A field value as a model gives it, as Drupal stores it: one way for all.
 *
 * The content, batch and import tools each had a copy, and they drifted:
 * only the import took a link as "/about", and it turned a CSV "false" into
 * TRUE and "abc" into 0. A value is changed here only when its meaning is
 * plain; anything else is left as given, so Drupal's validation refuses it
 * and says why.
 */
final class FieldValue {

  /**
   * The value for the field.
   *
   * With the entity field manager, a field that holds paragraphs takes them
   * inline too, as {"type": "text", "field_body": "..."}: the batch tool
   * could not, as only the content tool had that code.
   *
   * @throws \InvalidArgumentException
   *   When an inline item names a field its type does not have.
   */
  public static function normalize(EntityTypeManagerInterface $entityTypeManager, FieldDefinitionInterface $definition, mixed $value, ?EntityFieldManagerInterface $entityFieldManager = NULL): mixed {
    if ($definition->getType() === 'entity_reference_revisions') {
      return $entityFieldManager === NULL ? $value : self::inlineEntities($entityTypeManager, $entityFieldManager, $definition, $value);
    }
    return match ($definition->getType()) {
      'text', 'text_long', 'text_with_summary' => TextFormat::fieldValue($entityTypeManager, $value),
      'entity_reference', 'image', 'file' => is_array($value) ? $value : ['target_id' => self::whole($value)],
      'link' => is_array($value) ? $value : ['uri' => self::linkUri((string) $value)],
      'datetime' => is_array($value) ? $value : ['value' => $value],
      'boolean' => is_string($value) || is_int($value) ? (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value) : $value,
      'integer' => self::whole($value),
      'decimal', 'float' => is_string($value) && is_numeric(trim($value)) ? (float) $value : $value,
      default => $value,
    };
  }

  /**
   * The field a model means by a name: as given, or with field_ before it.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $definitions
   *   The bundle's fields, by name.
   * @param string $name
   *   The name given.
   *
   * @return string|null
   *   The field's name, or NULL when the bundle has neither.
   */
  public static function fieldName(array $definitions, string $name): ?string {
    if (isset($definitions[$name])) {
      return $name;
    }
    return isset($definitions['field_' . $name]) ? 'field_' . $name : NULL;
  }

  /**
   * Why some of the names are no fields of the bundle, or NULL.
   *
   * A name the bundle does not have was dropped by the batch tool, and
   * the call said success.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $definitions
   *   The bundle's fields, by name.
   * @param string[] $names
   *   The names given.
   * @param string $what
   *   The bundle, for the message: "node article".
   */
  public static function unknown(array $definitions, array $names, string $what): ?string {
    $unknown = array_values(array_filter(array_map('strval', $names), static fn(string $name): bool => self::fieldName($definitions, $name) === NULL));
    if ($unknown === []) {
      return NULL;
    }
    $own = array_keys(array_filter($definitions, static fn(FieldDefinitionInterface $d): bool => !$d->getFieldStorageDefinition()->isBaseField()));
    return sprintf('%s has no field %s. Its fields: %s.', ucfirst($what), implode(', ', $unknown), $own === [] ? 'none besides title and status' : implode(', ', $own));
  }

  /**
   * A link as a model gives it: "/about" or "about" is a path of the site.
   */
  public static function linkUri(string $value): string {
    $value = trim($value);
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $value)) {
      return $value;
    }
    return 'internal:/' . ltrim($value, '/');
  }

  /**
   * A whole number given as text, as a number; anything else as given.
   */
  private static function whole(mixed $value): mixed {
    return is_string($value) && preg_match('/^\s*-?\d+\s*$/', $value) ? (int) $value : $value;
  }

  /**
   * Items with a "type" made as new entities; anything else as given.
   *
   * ['target_id' => ...] references to existing entities pass through.
   * Layout Paragraphs: an item may have a "_ref" (a local name), and
   * another item names it as "parent_ref" in
   * behavior_settings.layout_paragraphs; it becomes the real parent_uuid.
   * The new entities are saved with the entity that holds them.
   */
  private static function inlineEntities(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, FieldDefinitionInterface $definition, mixed $value): mixed {
    if (!is_array($value)) {
      return $value;
    }
    $targetType = $definition->getSetting('target_type') ?? 'paragraph';
    if (!$entityTypeManager->hasDefinition($targetType)) {
      return $value;
    }

    $refMap = [];
    $created = [];
    $result = [];
    foreach (array_is_list($value) ? $value : [$value] as $item) {
      if (!is_array($item) || empty($item['type'])) {
        $result[] = $item;
        continue;
      }
      $ref = $item['_ref'] ?? NULL;
      unset($item['_ref']);
      $entity = self::inlineEntity($entityTypeManager, $entityFieldManager, $targetType, $item);
      $result[] = $entity;
      $created[] = $entity;
      if ($ref !== NULL) {
        $refMap[$ref] = $entity->uuid();
      }
    }

    foreach ($refMap === [] ? [] : $created as $entity) {
      if (!$entity instanceof ParagraphInterface) {
        continue;
      }
      $settings = $entity->getAllBehaviorSettings();
      $parentRef = $settings['layout_paragraphs']['parent_ref'] ?? NULL;
      if ($parentRef !== NULL && isset($refMap[$parentRef])) {
        $settings['layout_paragraphs']['parent_uuid'] = $refMap[$parentRef];
        unset($settings['layout_paragraphs']['parent_ref']);
        $entity->setAllBehaviorSettings($settings);
      }
    }
    return $result;
  }

  /**
   * One inline entity, its fields read as every field is.
   *
   * A field its type does not have was dropped without a word, and the
   * call said success. "body" finds "field_body", as at the top.
   */
  private static function inlineEntity(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, string $entityTypeId, array $data): EntityInterface {
    $bundle = (string) $data['type'];
    $behaviorSettings = $data['behavior_settings'] ?? NULL;
    unset($data['type'], $data['behavior_settings']);

    $definitions = $entityFieldManager->getFieldDefinitions($entityTypeId, $bundle);
    if (($why = self::unknown($definitions, array_keys($data), "a $entityTypeId of type '$bundle'")) !== NULL) {
      throw new \InvalidArgumentException($why);
    }
    $values = [$entityTypeManager->getDefinition($entityTypeId)->getKey('bundle') => $bundle];
    foreach ($data as $name => $given) {
      $field = self::fieldName($definitions, (string) $name);
      $values[$field] = self::normalize($entityTypeManager, $definitions[$field], $given, $entityFieldManager);
    }

    $entity = $entityTypeManager->getStorage($entityTypeId)->create($values);
    if (is_array($behaviorSettings) && $entity instanceof ParagraphInterface) {
      $entity->setAllBehaviorSettings($behaviorSettings);
    }
    return $entity;
  }

}
