<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;

/**
 * The list inputs of a new field, read the same way by every field tool.
 *
 * The structure and the paragraphs field tools each had their own copy of
 * this, and the paragraphs copy lagged behind: options given as
 * {"value": ..., "label": ...} failed there. A bundle that does not exist
 * was stored as a target, and the field then referred to nothing.
 */
final class FieldInput {

  /**
   * The field types the field tools add, by the name a model gives.
   *
   * One table for the structure and the paragraphs tools: the paragraphs
   * copy lacked timestamp, and took a type whose module was off.
   */
  public const TYPES = [
    'string' => [
      'type' => 'string',
      'widget' => 'string_textfield',
      'formatter' => 'string',
      'storage_settings' => ['max_length' => 255],
    ],
    'string_long' => [
      'type' => 'string_long',
      'widget' => 'string_textarea',
      'formatter' => 'basic_string',
      'storage_settings' => [],
    ],
    'text' => [
      'type' => 'text',
      'widget' => 'text_textfield',
      'formatter' => 'text_default',
      'storage_settings' => ['max_length' => 255],
    ],
    'text_long' => [
      'type' => 'text_long',
      'widget' => 'text_textarea',
      'formatter' => 'text_default',
      'storage_settings' => [],
    ],
    'text_with_summary' => [
      'type' => 'text_with_summary',
      'widget' => 'text_textarea_with_summary',
      'formatter' => 'text_default',
      'storage_settings' => [],
    ],
    'integer' => [
      'type' => 'integer',
      'widget' => 'number',
      'formatter' => 'number_integer',
      'storage_settings' => [],
    ],
    'decimal' => [
      'type' => 'decimal',
      'widget' => 'number',
      'formatter' => 'number_decimal',
      'storage_settings' => ['precision' => 10, 'scale' => 2],
    ],
    'float' => [
      'type' => 'float',
      'widget' => 'number',
      'formatter' => 'number_decimal',
      'storage_settings' => [],
    ],
    'boolean' => [
      'type' => 'boolean',
      'widget' => 'boolean_checkbox',
      'formatter' => 'boolean',
      'storage_settings' => [],
    ],
    'email' => [
      'type' => 'email',
      'widget' => 'email_default',
      'formatter' => 'basic_string',
      'storage_settings' => [],
    ],
    'link' => [
      'type' => 'link',
      'widget' => 'link_default',
      'formatter' => 'link',
      'storage_settings' => [],
    ],
    'datetime' => [
      'type' => 'datetime',
      'widget' => 'datetime_default',
      'formatter' => 'datetime_default',
      'storage_settings' => ['datetime_type' => 'datetime'],
    ],
    'date' => [
      'type' => 'datetime',
      'widget' => 'datetime_default',
      'formatter' => 'datetime_default',
      'storage_settings' => ['datetime_type' => 'date'],
    ],
    'timestamp' => [
      'type' => 'timestamp',
      'widget' => 'datetime_timestamp',
      'formatter' => 'timestamp',
      'storage_settings' => [],
    ],
    'image' => [
      'type' => 'image',
      'widget' => 'image_image',
      'formatter' => 'image',
      'storage_settings' => ['target_type' => 'file'],
    ],
    'file' => [
      'type' => 'file',
      'widget' => 'file_generic',
      'formatter' => 'file_default',
      'storage_settings' => ['target_type' => 'file'],
    ],
    'entity_reference' => [
      'type' => 'entity_reference',
      'widget' => 'entity_reference_autocomplete',
      'formatter' => 'entity_reference_label',
      'storage_settings' => [],
    ],
    // A field that holds paragraphs, on content or in another paragraph
    // type. A plain entity_reference to paragraphs is not one Paragraphs
    // supports. Many by default, as paragraph fields are.
    'paragraphs' => [
      'type' => 'entity_reference_revisions',
      'widget' => 'paragraphs',
      'formatter' => 'entity_reference_revisions_entity_view',
      'storage_settings' => [],
      'target_type' => 'paragraph',
      'cardinality' => -1,
    ],
    'list_string' => [
      'type' => 'list_string',
      'widget' => 'options_select',
      'formatter' => 'list_default',
      'storage_settings' => ['allowed_values' => []],
    ],
    'list_integer' => [
      'type' => 'list_integer',
      'widget' => 'options_select',
      'formatter' => 'list_default',
      'storage_settings' => ['allowed_values' => []],
    ],
  ];

  /**
   * The names in TYPES whose field type this site has.
   *
   * A type whose module is not enabled cannot be added: list fields need
   * Options, dates need Datetime, and so on.
   *
   * @return string[]
   *   Names.
   */
  public static function availableTypes(FieldTypePluginManagerInterface $fieldTypes, EntityTypeManagerInterface $entityTypes): array {
    return array_keys(array_filter(self::TYPES, static fn(array $type): bool => $fieldTypes->hasDefinition($type['type'])
      && (!isset($type['target_type']) || $entityTypes->hasDefinition($type['target_type']))));
  }

  /**
   * What a reference field of this type points at, or NULL if it is none.
   *
   * A type with a fixed target (paragraphs) takes no other.
   */
  public static function referenceTarget(array $type, mixed $given): ?string {
    if (!in_array($type['type'], ['entity_reference', 'entity_reference_revisions'], TRUE)) {
      return NULL;
    }
    return $type['target_type'] ?? (is_string($given) && $given !== '' ? $given : 'node');
  }

  /**
   * The options of a list field, as Drupal stores them: value => label.
   *
   * Takes labels (["Draft", "Final"]), whole numbers ([1, 2]), items
   * ({"value": "d", "label": "Draft"}), or value => label already.
   *
   * @throws \InvalidArgumentException
   *   When an item is not an option.
   */
  public static function allowedValues(mixed $values): array {
    if (!is_array($values)) {
      throw new \InvalidArgumentException('allowed_values must be a list of options.');
    }
    if (!array_is_list($values)) {
      return $values;
    }
    // A number is its own key, which is what a list of numbers needs. A
    // text gets a key made from the label.
    $result = [];
    foreach ($values as $position => $value) {
      if (is_array($value)) {
        $itemValue = $value['value'] ?? NULL;
        $itemLabel = $value['label'] ?? NULL;
        if (is_scalar($itemValue) && (string) $itemValue !== '' && ($itemLabel === NULL || is_scalar($itemLabel))) {
          $result[is_float($itemValue) ? (string) $itemValue : $itemValue] = (string) ($itemLabel ?? $itemValue);
          continue;
        }
        // A label alone is as a plain label.
        $value = $itemValue === NULL && is_scalar($itemLabel) && (string) $itemLabel !== '' ? $itemLabel : NULL;
      }
      if (!is_scalar($value)) {
        throw new \InvalidArgumentException('allowed_values: item ' . ($position + 1) . ' is not an option. Give each option as a text, a whole number, or {"value": ..., "label": ...}.');
      }
      if (is_int($value) || is_float($value)) {
        $result[$value] = (string) $value;
        continue;
      }
      $key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string) $value));
      $result[$key] = (string) $value;
    }
    return $result;
  }

  /**
   * The bundles a reference field may point to, each one that exists.
   *
   * Takes a list (["article", "page"]), one name or several with commas,
   * or Drupal's own map ({"article": "article"}).
   *
   * @return string[]
   *   The bundle names, keyed by themselves, as Drupal stores them.
   *
   * @throws \InvalidArgumentException
   *   When a bundle or the target type does not exist.
   */
  public static function targetBundles(mixed $given, string $targetType, EntityTypeManagerInterface $entityTypeManager): array {
    if ($given === NULL || $given === '' || $given === []) {
      return [];
    }
    if (is_string($given)) {
      $given = array_map('trim', explode(',', $given));
    }
    if (!is_array($given)) {
      throw new \InvalidArgumentException('target_bundles must be a list of bundle names, like ["article", "page"].');
    }
    $names = array_is_list($given) ? $given : array_keys($given);
    $definition = $entityTypeManager->getDefinition($targetType, FALSE);
    if ($definition === NULL) {
      throw new \InvalidArgumentException("target_type '$targetType' is not an entity type of this site.");
    }
    $bundleType = $definition->getBundleEntityType();
    $known = $bundleType === NULL ? [$targetType] : array_map('strval', array_keys($entityTypeManager->getStorage($bundleType)->loadMultiple()));
    $unknown = array_values(array_diff(array_map('strval', $names), $known));
    if ($unknown !== []) {
      throw new \InvalidArgumentException("target_bundles: $targetType has no bundle " . implode(', ', $unknown)
        . '. Give machine names. Its bundles: ' . ($known === [] ? 'none yet' : implode(', ', $known)) . '.');
    }
    $names = array_map('strval', $names);
    return array_combine($names, $names);
  }

}
