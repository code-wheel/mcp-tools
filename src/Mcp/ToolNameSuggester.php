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
 * Names are compared by their words, in any order. Nothing is run for the
 * model: it gets names, and calls the one it meant.
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
