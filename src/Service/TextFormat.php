<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * The text format to save a text with.
 *
 * The tools saved every text they were given as a plain string with the
 * format "basic_html". A site that has no such format (the minimal profile,
 * or a site with formats of its own) then stored the text and showed
 * nothing: Drupal renders a text whose format is missing as empty.
 */
final class TextFormat {

  /**
   * Whether the current user may use a format (TRUE where no one can ask).
   */
  private static function mayUse(object $format): bool {
    if (!\Drupal::hasContainer() || !method_exists($format, 'access')) {
      return TRUE;
    }
    return (bool) $format->access('use');
  }

  /**
   * Picks a format the site has.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param string|null $asked
   *   The format the caller named, if it named one.
   *
   * @return string
   *   The format that was asked for when the site has it, it is enabled and
   *   the user may use it. Else "basic_html" on the same terms, as before.
   *   Else the site's fallback format, which every site has and every user
   *   may use.
   */
  public static function resolve(EntityTypeManagerInterface $entityTypeManager, ?string $asked = NULL): string {
    // Without the filter module there are no formats to choose from.
    if (!$entityTypeManager->hasDefinition('filter_format')) {
      return $asked ?? 'basic_html';
    }
    $storage = $entityTypeManager->getStorage('filter_format');
    foreach (array_filter([$asked, 'basic_html']) as $id) {
      $format = $storage->load($id);
      // Only a format the user may use: Drupal refuses another on save.
      if ($format !== NULL && $format->status() && self::mayUse($format)) {
        return $id;
      }
    }
    foreach ($storage->loadMultiple() ?: [] as $format) {
      if ($format->isFallbackFormat()) {
        return (string) $format->id();
      }
    }
    return $asked ?? 'basic_html';
  }

  /**
   * A value for a text field: a plain string gets a format the site has.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param mixed $value
   *   A string, or an array as the field takes it.
   *
   * @return mixed
   *   For a string: its value and a format. For {"value": ..., "format":
   *   ...}: the same with a format the site has. Anything else as it is.
   */
  public static function fieldValue(EntityTypeManagerInterface $entityTypeManager, mixed $value): mixed {
    if (!is_array($value)) {
      return ['value' => $value, 'format' => self::resolve($entityTypeManager)];
    }
    if (isset($value['format']) && is_string($value['format'])) {
      $value['format'] = self::resolve($entityTypeManager, $value['format']);
    }
    return $value;
  }

}
