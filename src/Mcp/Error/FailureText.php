<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Error;

/**
 * A long failure text, shortened for a model: the start and the end stay.
 *
 * Drupal's own messages can list every plugin id there is (4,153
 * characters for a node of a type that is gone), and the answer is sent
 * again with every turn after it. The start says what is wrong, and the
 * way on ("Did you mean ...", a type's fields) is at the end, so only the
 * middle is cut. The structured content keeps the whole text.
 */
final class FailureText {

  /**
   * Texts up to this long are left as they are.
   */
  private const MAX = 1500;

  /**
   * The text, its middle cut when it is longer than MAX.
   */
  public static function short(string $text): string {
    $length = mb_strlen($text);
    if ($length <= self::MAX) {
      return $text;
    }
    $start = self::toWord(mb_substr($text, 0, 1000), TRUE);
    $end = self::toWord(mb_substr($text, -400), FALSE);
    $cut = $length - mb_strlen($start) - mb_strlen($end);
    return rtrim($start, " ,;:") . ' ... (' . number_format($cut) . ' characters cut) ... ' . ltrim($end, " ,;:");
  }

  /**
   * A piece that ends (or, for the end, starts) on a word.
   */
  private static function toWord(string $piece, bool $atEnd): string {
    $space = $atEnd ? mb_strrpos($piece, ' ') : mb_strpos($piece, ' ');
    if ($space === FALSE) {
      return $piece;
    }
    return $atEnd ? mb_substr($piece, 0, $space) : mb_substr($piece, $space + 1);
  }

}
