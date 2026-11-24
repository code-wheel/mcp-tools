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

  /**
   * Each category's permission is the permission of the module its tools are in.
   *
   * The base module defines the categories its own tools use; a category
   * only a submodule uses is that submodule's, so the permissions page lists
   * it only when the submodule is on, and an uninstall revokes it.
   */
  public function testAllToolCategoriesHavePermissions(): void {
    $moduleRoot = dirname(__DIR__, 4);
    $owners = $this->discoverToolCategories($moduleRoot);
    $this->assertNotEmpty($owners, 'No MCP tool categories were discovered.');

    foreach ($owners as $category => $modules) {
      $owner = in_array('mcp_tools', $modules, TRUE) ? 'mcp_tools' : NULL;
      if ($owner === NULL) {
        $this->assertCount(1, $modules, "The category '$category' is used by more than one submodule: " . implode(', ', $modules));
        $owner = $modules[0];
      }
      $this->assertContains("mcp_tools use $category", $this->permissionsOf($moduleRoot, $owner), "The permission of the category '$category' is not defined by $owner, whose tools use it.");
      if ($owner !== 'mcp_tools') {
        $this->assertNotContains("mcp_tools use $category", $this->permissionsOf($moduleRoot, 'mcp_tools'), "The base module defines the permission of '$category', a category only $owner uses.");
      }
    }
  }

  /**
   * The permission names a module defines.
   *
   * @return string[]
   *   Names.
   */
  private function permissionsOf(string $moduleRoot, string $module): array {
    $file = $module === 'mcp_tools' ? "$moduleRoot/mcp_tools.permissions.yml" : "$moduleRoot/modules/$module/$module.permissions.yml";
    $yaml = is_file($file) ? (string) file_get_contents($file) : '';
    preg_match_all('/^([a-z][^:\n]+):\s*$/m', $yaml, $matches);
    return $matches[1];
  }

  /**
   * The MCP_CATEGORY of each tool, and the modules whose tools use it.
   *
   * @return array<string, string[]>
   *   Category => modules.
   */
  private function discoverToolCategories(string $moduleRoot): array {
    $owners = [];
    $dirs = ['mcp_tools' => $moduleRoot . '/src/Plugin/tool/Tool'];
    foreach (glob($moduleRoot . '/modules/*/src/Plugin/tool/Tool', GLOB_ONLYDIR) ?: [] as $dir) {
      $dirs[basename(dirname($dir, 4))] = $dir;
    }
    foreach ($dirs as $module => $dir) {
      foreach (glob($dir . '/*.php') ?: [] as $file) {
        $contents = (string) file_get_contents($file);
        if (preg_match("/protected const MCP_CATEGORY = '([^']+)'/m", $contents, $matches) === 1) {
          $owners[$matches[1]][$module] = $module;
        }
      }
    }
    ksort($owners);
    return array_map(fn($modules) => array_values($modules), $owners);
  }

}
