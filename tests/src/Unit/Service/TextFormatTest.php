<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\TextFormat;
use Drupal\Tests\UnitTestCase;

/**
 * No tool saves a text with a format it did not check.
 *
 * What the class does on a real site is in TextFormatKernelTest.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\TextFormat::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class TextFormatTest extends UnitTestCase {

  /**
   * Six places in five services wrote "basic_html" themselves.
   *
   * A site without that format then showed the text as empty. They ask
   * TextFormat now, and this keeps a seventh from being written.
   */
  public function testNoCodeNamesAFormatItself(): void {
    $moduleRoot = dirname(__DIR__, 4);
    $found = [];
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($moduleRoot, \FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
      $path = str_replace('\\', '/', $file->getPathname());
      if (!str_ends_with($path, '.php') || !str_contains($path, '/src/') || str_contains($path, '/tests/') || str_contains($path, '/vendor/') || str_ends_with($path, '/src/Service/TextFormat.php')) {
        continue;
      }
      foreach (file($path) ?: [] as $number => $line) {
        if (preg_match('/(=>|\?\?|=)\s*\'(basic_html|full_html|restricted_html|plain_text)\'/', $line)) {
          $found[] = substr($path, strlen($moduleRoot) + 1) . ':' . ($number + 1);
        }
      }
    }
    $this->assertSame([], $found, 'A text format is named in code. Ask TextFormat::resolve() for one the site has.');
  }

  /**
   * Without the filter module there is nothing to choose from.
   */
  public function testWithoutFormatsItStaysAsItWas(): void {
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->willReturn(FALSE);
    $this->assertSame('basic_html', TextFormat::resolve($manager));
    $this->assertSame('rich', TextFormat::resolve($manager, 'rich'));
    $this->assertSame(['value' => 'Hello', 'format' => 'basic_html'], TextFormat::fieldValue($manager, 'Hello'));
  }

}
