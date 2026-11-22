<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Tool;

use Drupal\mcp_tools\Mcp\McpToolsServerFactory;
use Drupal\Tests\UnitTestCase;

/**
 * Static checks for Tool API plugin metadata.
 *
 * This intentionally does not bootstrap Drupal. It scans tool plugin source
 * files to prevent accidental mislabeling of read/write operations.
 *
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ToolOperationDeclarationTest extends UnitTestCase {

  /**
   * First words of tool classes that change something.
   */
  private const WRITE_WORDS = [
    'Activate',
    'Add',
    'Allow',
    'Apply',
    'Assign',
    'Block',
    'Cancel',
    'Clear',
    'Clone',
    'Configure',
    'Create',
    'Delete',
    'Disable',
    'Enable',
    'Export',
    'Fetch',
    'Generate',
    'Grant',
    'Import',
    'Index',
    'Invalidate',
    'Place',
    'Publish',
    'Rebuild',
    'Regenerate',
    'Reindex',
    'Remove',
    'Reset',
    'Revoke',
    'Run',
    'Scaffold',
    'Schedule',
    'Set',
    'Translate',
    'Update',
    'Upload',
  ];

  /**
   * First words of tool classes that only read.
   */
  private const READ_WORDS = [
    'Analyze',
    'Check',
    'ContentAudit',
    'Discover',
    'Find',
    'Get',
    'List',
    'Preview',
    'Search',
    'SecurityAudit',
    'Validate',
  ];

  #[\PHPUnit\Framework\Attributes\CoversNothing]
  public function testOperationDeclarationsMatchIntent(): void {
    $moduleRoot = dirname(__DIR__, 4);
    $toolFiles = $this->findToolPluginFiles($moduleRoot);
    $this->assertNotEmpty($toolFiles, 'Expected to find Tool API plugin files.');

    // Every tool class has to start with a word from one of these two lists.
    // A word in neither list used to mean the tool was not checked at all:
    // BlockUser, ActivateUser and AllowCustomLayouts were declared as read
    // operations for that reason.
    $writePrefixes = self::WRITE_WORDS;
    $readPrefixes = self::READ_WORDS;

    $writeLikeOperations = ['Write', 'Trigger', 'Delete'];

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

      $classified = FALSE;
      foreach ($writePrefixes as $prefix) {
        if (str_starts_with($className, $prefix)) {
          $this->assertContains($operation, $writeLikeOperations, "Write-intent tool must be Write/Trigger: {$path}");
          $classified = TRUE;
          break;
        }
      }

      foreach ($readPrefixes as $prefix) {
        if (!$classified && str_starts_with($className, $prefix)) {
          $this->assertNotContains($operation, $writeLikeOperations, "Read-intent tool must not be Write/Trigger: {$path}");
          $classified = TRUE;
          break;
        }
      }
      $this->assertTrue($classified, "No rule says whether {$className} reads or writes. Add its first word to a list in this test: {$path}");

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
   * A description that points to another tool uses that tool's name.
   *
   * Descriptions used to say "Use ListContentTypes", the name of the PHP
   * class. A client only sees tool names, and models made up names such as
   * mcp_list_content_types from it.
   */
  #[\PHPUnit\Framework\Attributes\CoversNothing]
  public function testDescriptionsNameOtherToolsByToolName(): void {
    $moduleRoot = dirname(__DIR__, 4);
    $known = ['mcp_tools' => TRUE];
    foreach (glob($moduleRoot . '/modules/*', GLOB_ONLYDIR) ?: [] as $dir) {
      $known[basename($dir)] = TRUE;
    }
    $attributes = [];
    foreach ($this->findToolPluginFiles($moduleRoot) as $path) {
      if (preg_match('/#\[Tool\((.*?)\n\)\]\s*\n(?:final )?class /s', (string) file_get_contents($path), $attribute)
        && preg_match("/\bid:\s*'([^']+)'/", $attribute[1], $id)) {
        $known[$id[1]] = TRUE;
        $attributes[$path] = $attribute[1];
      }
    }
    $this->assertGreaterThan(200, count($attributes), 'Expected to find the tool attributes.');

    $classLike = '/\b(?:' . implode('|', [...self::WRITE_WORDS, ...self::READ_WORDS]) . ')[A-Z][A-Za-z]+\b/';
    foreach ($attributes as $path => $attribute) {
      preg_match_all($classLike, $attribute, $classNames);
      $this->assertSame([], array_values(array_unique($classNames[0])), "A description names a tool by its PHP class. Use the tool name (mcp_...) in: {$path}");

      preg_match_all('/\bmcp_[a-z0-9_]+\b/', $attribute, $toolNames);
      $unknown = array_diff(array_unique($toolNames[0]), array_keys($known));
      $this->assertSame([], array_values($unknown), "A description names a tool that does not exist in: {$path}");
    }
  }

  /**
   * The server's instructions say of deleting only what holds for the tools.
   *
   * They said "Deleting asks for an explicit confirm flag". Two tools have
   * such an input. A model that had read the sentence passed "confirm" to
   * mcp_delete_view ten times in one run of 2026-11-21 and was refused each
   * time: "Additional object properties are not allowed: confirm".
   */
  #[\PHPUnit\Framework\Attributes\CoversNothing]
  public function testInstructionsDoNotPromiseAConfirmFlagToEveryDeletingTool(): void {
    $withFlag = [];
    $without = [];
    foreach ($this->findToolPluginFiles(dirname(__DIR__, 4)) as $path) {
      if (!preg_match('/#\[Tool\((.*?)\n\)\]\s*\n(?:final )?class /s', (string) file_get_contents($path), $attribute)
        || !preg_match("/\bid:\s*'([^']+)'/", $attribute[1], $id)
        || !preg_match('/(^|_)delete(_|$)/', $id[1])) {
        continue;
      }
      if (preg_match("/'[a-z_]*confirm[a-z_]*'\s*=>\s*new InputDefinition/", $attribute[1])) {
        $withFlag[] = $id[1];
      }
      else {
        $without[] = $id[1];
      }
    }
    $this->assertGreaterThan(10, count($withFlag) + count($without), 'Expected to find the deleting tools.');
    $this->assertNotSame([], $without, 'Every deleting tool has a confirm input now: the sentence may come back.');

    foreach ([TRUE, FALSE] as $gatewayMode) {
      $text = McpToolsServerFactory::instructions($gatewayMode);
      $this->assertStringNotContainsString('Deleting asks for an explicit confirm flag', $text);
      // What is true: a tool that wants a confirmation has an input for it.
      $this->assertStringContainsString('only when the tool has such an input', $text);
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

      // Tools of test modules are fixtures, not tools this project ships.
      if (!str_contains($path, '/src/Plugin/tool/Tool/') || str_contains($path, '/tests/modules/')) {
        continue;
      }

      $toolFiles[] = $file->getPathname();
    }

    sort($toolFiles);
    return $toolFiles;
  }

}

