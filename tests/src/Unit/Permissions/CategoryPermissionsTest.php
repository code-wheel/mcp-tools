<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Permissions;

use Drupal\Tests\UnitTestCase;

/**
 * Verifies every MCP Tools category has a corresponding permission defined.
 *
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class CategoryPermissionsTest extends UnitTestCase {

  public function testAllToolCategoriesHavePermissions(): void {
    $moduleRoot = dirname(__DIR__, 4);
    $permissionsPath = $moduleRoot . '/mcp_tools.permissions.yml';

    $permissionsYaml = file_get_contents($permissionsPath);
    $this->assertNotFalse($permissionsYaml, 'Unable to read mcp_tools.permissions.yml');

    $categories = $this->discoverToolCategories($moduleRoot);
    $this->assertNotEmpty($categories, 'No MCP tool categories were discovered.');

    foreach ($categories as $category) {
      $permissionKey = "mcp_tools use {$category}:";
      $this->assertStringContainsString(
        $permissionKey,
        $permissionsYaml,
        "Missing permission definition for MCP category '{$category}'."
      );
    }
  }

  /**
   * Discovers all MCP_CATEGORY values used by Tool API plugin classes.
   *
   * @return string[]
   *   Unique category strings.
   */
  private function discoverToolCategories(string $moduleRoot): array {
    $categories = [];

    $pluginDirs = [];
    $baseDir = $moduleRoot . '/src/Plugin/tool/Tool';
    if (is_dir($baseDir)) {
      $pluginDirs[] = $baseDir;
    }

    $submoduleDirs = glob($moduleRoot . '/modules/*/src/Plugin/tool/Tool', GLOB_ONLYDIR) ?: [];
    foreach ($submoduleDirs as $dir) {
      if (is_dir($dir)) {
        $pluginDirs[] = $dir;
      }
    }

    foreach ($pluginDirs as $dir) {
      foreach (glob($dir . '/*.php') ?: [] as $file) {
        $contents = file_get_contents($file);
        if ($contents === FALSE) {
          continue;
        }
        if (preg_match("/protected const MCP_CATEGORY = '([^']+)'/m", $contents, $matches) !== 1) {
          continue;
        }
        $categories[$matches[1]] = TRUE;
      }
    }

    $list = array_keys($categories);
    sort($list);
    return $list;
  }

}

