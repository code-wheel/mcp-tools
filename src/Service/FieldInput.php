<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

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
