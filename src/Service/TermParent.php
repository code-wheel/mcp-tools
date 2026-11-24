<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * The parent a new term is given, by id or by name, for every term tool.
 *
 * The tools that make several terms took a parent's id only. Given a name,
 * one answered "Tool execution failed." and nothing else, and the other
 * stored a parent that was no term. A name may also be a term made earlier
 * in the same call, so a small tree goes in one call.
 */
final class TermParent {

  /**
   * The parent's term id (0 for none), or why there is none.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param string $vocabulary
   *   The vocabulary of the new term.
   * @param mixed $given
   *   A term id, or the name of a term of the vocabulary.
   * @param array<string, int> $madeNow
   *   Terms made earlier in this call: name => id.
   *
   * @return int|string
   *   The id, or a sentence saying what is wrong.
   */
  public static function resolve(EntityTypeManagerInterface $entityTypeManager, string $vocabulary, mixed $given, array $madeNow = []): int|string {
    $given = is_scalar($given) ? trim((string) $given) : '';
    if ($given === '' || $given === '0') {
      return 0;
    }
    $storage = $entityTypeManager->getStorage('taxonomy_term');
    // Only digits are looked up as an id: a name given to an integer column
    // is a database error on PostgreSQL.
    if (ctype_digit($given)) {
      $term = $storage->load((int) $given);
      if ($term !== NULL && $term->bundle() === $vocabulary) {
        return (int) $term->id();
      }
    }
    if (isset($madeNow[$given])) {
      return $madeNow[$given];
    }
    $ids = array_values($storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', $vocabulary)
      ->condition('name', $given)
      ->execute());
    if (count($ids) === 1) {
      return (int) $ids[0];
    }
    if ($ids !== []) {
      return "More than one term of '$vocabulary' is named '$given': give the parent's id, one of " . implode(', ', $ids) . '.';
    }
    return "There is no term '$given' in vocabulary '$vocabulary' to be the parent, by id or by name. mcp_structure_get_vocabulary lists its terms.";
  }

}
