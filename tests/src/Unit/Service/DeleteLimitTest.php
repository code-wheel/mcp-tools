<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Tests\UnitTestCase;

/**
 * A service method that deletes counts against a stricter limit.
 *
 * Nearly all asked canWrite() for a plain write, so a site's lower limit
 * for deletes did not hold. Deleting a piece of the site's structure may
 * count as a structure change, the other stricter limit.
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class DeleteLimitTest extends UnitTestCase {

  /**
   * No delete method asks for a plain write.
   */
  public function testDeletesAskForADelete(): void {
    $root = dirname(__DIR__, 4);
    $files = array_merge(glob("$root/src/Service/*.php"), glob("$root/modules/*/src/Service/*.php"));
    $this->assertGreaterThan(40, count($files));
    $wrong = [];
    $right = 0;
    foreach ($files as $file) {
      $method = '';
      foreach (explode("\n", (string) file_get_contents($file)) as $line) {
        if (preg_match('/function (\w+)\(/', $line, $match)) {
          $method = $match[1];
        }
        if (preg_match('/^delete[A-Z]/', $method) && preg_match('/->canWrite\((.*)\)/', $line, $match)) {
          preg_match("/^'(delete|structure)'/", $match[1]) ? $right++ : $wrong[] = basename($file) . '::' . $method;
        }
      }
    }
    $this->assertSame([], $wrong);
    $this->assertGreaterThan(3, $right);
  }

}
