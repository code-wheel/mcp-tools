<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\filter\FilterFormatInterface;

/**
 * Says what a text format will not show of a text that was just stored.
 *
 * A format drops the tags and attributes it does not allow when the text is
 * shown, and a format the user may not use is replaced when it is stored.
 * Both came back as plain success: of fourteen kinds of text written to
 * Basic HTML, ten lost a tag or an attribute and no answer said so.
 * What another filter does (an image that is not there) is not looked at.
 */
final class TextLoss {

  /**
   * How many tags and attributes are named.
   */
  private const MOST = 8;

  /**
   * What is lost in these fields, as sentences; empty when nothing is.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The stored entity.
   * @param array<string, mixed> $given
   *   What the call gave, by field name as the entity has it.
   */
  public static function notice(FieldableEntityInterface $entity, array $given): string {
    $said = [];
    foreach ($given as $name => $value) {
      $name = (string) $name;
      $type = $entity->hasField($name) ? $entity->getFieldDefinition($name)->getType() : '';
      if (!in_array($type, ['text', 'text_long', 'text_with_summary'], TRUE)) {
        continue;
      }
      foreach ($entity->get($name) as $item) {
        $id = (string) $item->format;
        $asked = is_array($value) && is_string($value['format'] ?? NULL) ? $value['format'] : $id;
        if ($asked !== $id) {
          $said[] = "The text format '$asked' cannot be used here, so $name was stored as '$id'.";
        }
        $format = \Drupal::entityTypeManager()->hasDefinition('filter_format') ? \Drupal::entityTypeManager()->getStorage('filter_format')->load($id) : NULL;
        $lost = $format instanceof FilterFormatInterface ? self::lost((string) $item->value, $format) : [];
        if ($lost !== []) {
          $more = count($lost) - self::MOST;
          $said[] = "The text format '$id' does not let these through in $name: " . implode(', ', array_slice($lost, 0, self::MOST)) . ($more > 0 ? " and $more more" : '') . '. The text is stored as given; a reader does not get them.';
        }
      }
    }
    return implode(' ', array_unique($said));
  }

  /**
   * The tags and attributes of a text that a format does not allow.
   *
   * @return string[]
   *   Such as "<table>" and "class on <p>".
   */
  private static function lost(string $text, FilterFormatInterface $format): array {
    if (!str_contains($text, '<')) {
      return [];
    }
    $restrictions = $format->getHtmlRestrictions();
    if ($restrictions === FALSE || !isset($restrictions['allowed'])) {
      return [];
    }
    $allowed = $restrictions['allowed'];
    $everywhere = is_array($allowed['*'] ?? NULL) ? $allowed['*'] : [];
    $lost = [];
    $body = Html::load($text)->getElementsByTagName('body')->item(0);
    foreach ($body?->getElementsByTagName('*') ?? [] as $element) {
      $tag = strtolower($element->nodeName);
      if (!array_key_exists($tag, $allowed)) {
        // The parser adds a table's body by itself.
        if ($tag !== 'tbody' || stripos($text, '<tbody') !== FALSE) {
          $lost["<$tag>"] = "<$tag>";
        }
        continue;
      }
      foreach ($element->attributes ?? [] as $attribute) {
        $name = strtolower($attribute->nodeName);
        if (!self::allows($allowed[$tag], $name) && !self::allows($everywhere, $name) || ($everywhere[$name] ?? NULL) === FALSE) {
          $lost["$name on <$tag>"] = "$name on <$tag>";
        }
      }
    }
    return array_values($lost);
  }

  /**
   * Whether a tag's rule lets an attribute through (its values not looked at).
   */
  private static function allows(mixed $rule, string $attribute): bool {
    if (!is_array($rule)) {
      return $rule === TRUE;
    }
    foreach ($rule as $name => $values) {
      if ($values !== FALSE && ($name === $attribute || (str_ends_with((string) $name, '*') && str_starts_with($attribute, rtrim((string) $name, '*'))))) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
