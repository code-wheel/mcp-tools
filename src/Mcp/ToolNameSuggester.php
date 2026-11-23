<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

/**
 * Finds the real tool names closest to a name that does not exist.
 *
 * A model often makes a tool name up from what it expects: the module left
 * out ("mcp_list_content_types"), another module put in, the words the
 * other way round ("mcp_config_get"), a letter missing. "Unknown tool" alone
 * sends it searching again. The closest real names let it go on at once.
 *
 * Names are compared by their words, in any order. For these the model
 * gets names, and calls the one it meant.
 *
 * Two mistakes are not answered with a name: the tool is run. That is a
 * name with only "tools_" left out, or only "tools_" put in, see meant().
 */
final class ToolNameSuggester {

  /**
   * Words every tool name has, which say nothing about the tool.
   */
  private const FILLER = ['mcp', 'tool'];

  /**
   * The closest names, the closest first.
   *
   * @param string $unknown
   *   The name that was used.
   * @param string[] $names
   *   The names that could have been meant: the tools this session has.
   * @param int $limit
   *   How many to return at most.
   *
   * @return string[]
   *   Up to $limit names. Empty when nothing is close.
   */
  public static function closest(string $unknown, array $names, int $limit = 3): array {
    $wanted = self::words($unknown);
    if ($wanted === []) {
      return [];
    }

    $scored = [];
    foreach ($names as $name) {
      $name = (string) $name;
      $have = self::words($name);
      if ($have === []) {
        continue;
      }
      $shared = 0.0;
      foreach ($wanted as $word) {
        $shared += self::bestMatch($word, $have);
      }
      $score = $shared / (count($wanted) + count($have) - $shared);
      if ($score >= 0.5) {
        $scored[] = [$score, levenshtein(strtolower($unknown), strtolower($name)), $name];
      }
    }

    usort($scored, static fn(array $a, array $b): int => [$b[0], $a[1], $a[2]] <=> [$a[0], $b[1], $b[2]]);
    return array_column(array_slice($scored, 0, max(0, $limit)), 2);
  }

  /**
   * The same name with "tools_" after "mcp_", for a name without it.
   *
   * Some of the tools are named mcp_tools_..., and most are not. A model
   * follows the many: it calls mcp_search_content right after it was shown
   * mcp_tools_search_content. In 161 runs of a local model, 64 of the 78
   * made-up names were this one mistake. Only one tool can be meant, so the
   * callers look this name up and run it, with every check a call by the
   * full name gets.
   *
   * @return string|null
   *   The name to look up. NULL for a name that does not start with "mcp_"
   *   or that has "tools_" already.
   */
  public static function withTools(string $name): ?string {
    if (!str_starts_with($name, 'mcp_') || str_starts_with($name, 'mcp_tools_') || strlen($name) < 5) {
      return NULL;
    }
    return 'mcp_tools_' . substr($name, 4);
  }

  /**
   * The same name with "tools_" after "mcp_" taken out, for a name with it.
   *
   * The reverse of withTools(): a model that has seen mcp_tools_search_content
   * calls mcp_tools_redirect_create for mcp_redirect_create (3 times in 34
   * runs on 2026-11-21).
   *
   * @return string|null
   *   The name to look up. NULL for a name that does not start with
   *   "mcp_tools_".
   */
  public static function withoutTools(string $name): ?string {
    if (!str_starts_with($name, 'mcp_tools_') || strlen($name) <= strlen('mcp_tools_')) {
      return NULL;
    }
    return 'mcp_' . substr($name, strlen('mcp_tools_'));
  }

  /**
   * The other name that is meant, for "tools_" too few or too many.
   *
   * @return string|null
   *   The name to look up, or NULL.
   */
  public static function meant(string $name): ?string {
    return self::withTools($name) ?? self::withoutTools($name);
  }

  /**
   * The one real name that is meant, from the names there are.
   *
   * First "tools_" too few or too many, then the module part of the name:
   * a model calls mcp_tools_list_field_types for
   * mcp_structure_list_field_types (77 of 124 made-up names in 1,295 runs of
   * the framework session, 2026-11-21). With the first word after "mcp_"
   * taken off, the rest must have two words at least and end exactly one
   * real name. Two or more and nothing is meant: the closest names answer.
   *
   * @param string $name
   *   The name that was used.
   * @param string[] $names
   *   The names there are.
   *
   * @return string|null
   *   The real name, or NULL.
   */
  public static function meantAmong(string $name, array $names): ?string {
    $known = array_flip($names);
    $simple = self::meant($name);
    if ($simple !== NULL && isset($known[$simple])) {
      return $simple;
    }
    if (!str_starts_with($name, 'mcp_') || !preg_match('/^mcp_[a-z0-9]+_((?:[a-z0-9]+_)+[a-z0-9]+)$/', $name, $match)) {
      return NULL;
    }
    $rest = '_' . $match[1];
    $found = array_values(array_filter($names, static fn(string $real): bool => $real !== $name && str_starts_with($real, 'mcp_') && str_ends_with($real, $rest)
      && substr_count(substr($real, 4, -strlen($rest)), '_') === 0));
    return count($found) === 1 ? $found[0] : NULL;
  }

  /**
   * The words of a name that say something, each in one form.
   *
   * @return string[]
   *   Lower-case words, plural endings dropped, filler words left out.
   */
  private static function words(string $name): array {
    $words = [];
    foreach (preg_split('/[^a-z0-9]+/', strtolower($name)) ?: [] as $word) {
      if (strlen($word) > 3 && str_ends_with($word, 's') && !str_ends_with($word, 'ss') && !str_ends_with($word, 'us')) {
        $word = substr($word, 0, -1);
      }
      if ($word !== '' && !in_array($word, self::FILLER, TRUE)) {
        $words[$word] = $word;
      }
    }
    return array_values($words);
  }

  /**
   * How well a word is matched by one of the words of a real name.
   *
   * @return float
   *   1 for the same word, 0.8 for a typing mistake in a longer word, else 0.
   */
  private static function bestMatch(string $word, array $have): float {
    if (in_array($word, $have, TRUE)) {
      return 1.0;
    }
    if (strlen($word) >= 5) {
      foreach ($have as $other) {
        if (strlen($other) >= 5 && levenshtein($word, $other) <= 2) {
          return 0.8;
        }
      }
    }
    return 0.0;
  }

}
