<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\mcp_tools\Mcp\Error\FailureText;
use Drupal\Tests\UnitTestCase;

/**
 * A long failure text keeps its start and its end; the middle is cut.
 *
 * Updating a node of a type that no longer exists gave 4,153 characters:
 * Drupal's own message lists every typed data plugin id. The answer stays
 * in the conversation and is sent again with every turn. The way on is at
 * the end ("Did you mean ...", a type's fields), so the end stays.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(FailureText::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class FailureTextTest extends UnitTestCase {

  /**
   * A short text is as it was; a long one loses its middle, not its end.
   */
  public function testLongTextKeepsItsStartAndEnd(): void {
    $plain = 'Failed to update content: the title is empty.';
    $this->assertSame($plain, FailureText::short($plain));

    // A field list well over the old 600 characters is not cut at all.
    $fields = 'Unknown field(s) on content type \'article\': body. Nothing was created. Its own fields: ' . implode(', ', array_map(static fn(int $i): string => "field_something_long_$i", range(1, 40))) . '.';
    $this->assertGreaterThan(600, mb_strlen($fields));
    $this->assertSame($fields, FailureText::short($fields));

    $long = 'Failed to update content: The "entity:node:article" plugin does not exist. Valid plugin IDs are: ' . str_repeat('entity_revision:some_plugin, ', 150) . 'Did you mean: access contextual links?';
    $cut = FailureText::short($long);
    $this->assertStringStartsWith('Failed to update content: The "entity:node:article" plugin does not exist.', $cut);
    $this->assertStringEndsWith('Did you mean: access contextual links?', $cut);
    $this->assertLessThan(1600, mb_strlen($cut));
    $this->assertMatchesRegularExpression('/ \.\.\. \([\d,]+ characters cut\) \.\.\. /', $cut);
  }

}
