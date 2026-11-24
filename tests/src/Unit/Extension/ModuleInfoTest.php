<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Extension;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;

/**
 * The base module and every submodule show as one group on the Extend page.
 *
 * They were split over "AI", "MCP" and "MCP Tools", so a site builder
 * looked in three places, and two names broke the "MCP Tools - X" pattern.
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ModuleInfoTest extends UnitTestCase {

  public function testOnePackageAndOneNamePattern(): void {
    $root = dirname(__DIR__, 4);
    $files = array_merge(["$root/mcp_tools.info.yml"], glob("$root/modules/*/*.info.yml") ?: []);
    $this->assertGreaterThan(30, count($files));
    foreach ($files as $file) {
      $info = Yaml::decode((string) file_get_contents($file));
      $this->assertSame('MCP Tools', $info['package'] ?? NULL, basename($file) . ' is in another package.');
      if (basename($file) !== 'mcp_tools.info.yml') {
        $this->assertMatchesRegularExpression('/^MCP Tools - \S/', (string) $info['name'], basename($file) . ' is not named "MCP Tools - X".');
      }
    }
  }

}
