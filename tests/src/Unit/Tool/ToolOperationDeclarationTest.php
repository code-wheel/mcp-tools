<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Tool;

use Drupal\Tests\UnitTestCase;

/**
 * Static checks for Tool API plugin metadata.
 *
 * This intentionally does not bootstrap Drupal. It scans tool plugin source
 * files to prevent accidental mislabeling of read/write operations.
 *
 * @group mcp_tools
 */
final class ToolOperationDeclarationTest extends UnitTestCase {

  /**
   * @coversNothing
   */
  public function testOperationDeclarationsMatchIntent(): void {
    $moduleRoot = dirname(__DIR__, 4);
    $toolFiles = $this->findToolPluginFiles($moduleRoot);
    $this->assertNotEmpty($toolFiles, 'Expected to find Tool API plugin files.');

    $writePrefixes = [
      'Add',
      'Apply',
      'Assign',
      'Cancel',
      'Clear',
      'Clone',
      'Configure',
      'Create',
      'Delete',
      'Disable',
      'Enable',
      'Export',
      'Grant',
      'Import',
      'Index',
      'Invalidate',
      'Place',
      'Rebuild',
      'Regenerate',
      'Reindex',
      'Remove',
      'Reset',
      'Revoke',
      'Run',
      'Schedule',
      'Set',
      'Update',
      'Upload',
    ];

    $readPrefixes = [
      'Analyze',
      'Check',
      'Get',
      'List',
    ];

    $writeLikeOperations = ['Write', 'Trigger'];

    foreach ($toolFiles as $path) {
      $contents = file_get_contents($path);
      $this->assertIsString($contents, 'Unable to read: ' . $path);

      if (!str_contains($contents, '#[Tool(')) {
        // Not a Tool API plugin file.
        continue;
      }

      preg_match('/operation:\s*ToolOperation::([A-Za-z_]+)/', $contents, $matches);
      $this->assertNotEmpty($matches[1] ?? NULL, 'Missing ToolOperation in: ' . $path);
      $operation = $matches[1];

      $className = pathinfo($path, PATHINFO_FILENAME);
      $isDestructive = preg_match('/destructive:\s*TRUE/', $contents) === 1;
      if ($isDestructive) {
        $this->assertContains($operation, $writeLikeOperations, "Destructive tools must be Write/Trigger: {$path}");
      }

      foreach ($writePrefixes as $prefix) {
        if (str_starts_with($className, $prefix)) {
          $this->assertContains($operation, $writeLikeOperations, "Write-intent tool must be Write/Trigger: {$path}");
          break;
        }
      }

      foreach ($readPrefixes as $prefix) {
        if (str_starts_with($className, $prefix)) {
          $this->assertNotContains($operation, $writeLikeOperations, "Read-intent tool must not be Write/Trigger: {$path}");
          break;
        }
      }

      if ($operation === 'Read') {
        $this->assertFalse(
          (bool) preg_match('/->canWrite\(|->checkWriteAccess\(|checkAdminAccess\(|WriteAccessTrait/', $contents),
          "Read tools should not reference write access checks: {$path}"
        );
      }

      // Validate MCP_WRITE_KIND values where explicitly set.
      preg_match('/protected const MCP_WRITE_KIND\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $contents, $kindMatches);
      if (!empty($kindMatches[1])) {
        $this->assertContains(
          $kindMatches[1],
          ['config', 'content', 'ops'],
          "Invalid MCP_WRITE_KIND value in: {$path}"
        );
      }
    }
  }

  /**
   * @return string[]
   *   Absolute paths to Tool API plugin PHP files.
   */
  private function findToolPluginFiles(string $moduleRoot): array {
    $toolFiles = [];

    if (!is_dir($moduleRoot)) {
      return $toolFiles;
    }

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($moduleRoot, \FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
      if (!$file->isFile()) {
        continue;
      }

      $path = str_replace('\\', '/', $file->getPathname());
      if (!str_ends_with($path, '.php')) {
        continue;
      }

      if (!str_contains($path, '/src/Plugin/tool/Tool/')) {
        continue;
      }

      $toolFiles[] = $file->getPathname();
    }

    sort($toolFiles);
    return $toolFiles;
  }

}

