<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Tool;

use Drupal\Tests\UnitTestCase;

/**
 * The docs name tools that exist.
 *
 * Six module READMEs listed their tools under names nobody could call:
 * "mcp_users_block_user" for mcp_block_user, "mcp_media_create" for
 * mcp_create_media. A person who copies a name from a README, or a model
 * that is given the README, gets "Unknown tool". Nothing compared the docs
 * with the code, so this does: it reads the tool ids from the plugin files
 * and every name that looks like a tool from the README files and the docs.
 *
 * It does not bootstrap Drupal.
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class DocsNameRealToolsTest extends UnitTestCase {

  /**
   * Names that look like a tool and are something else. Each with its why.
   */
  private const NOT_TOOLS = [
    // Other modules on drupal.org.
    'mcp_server',
    'mcp_server_tool_bridge',
    // The role and the user the remote settings form creates.
    'mcp_executor',
    // The query parameter for scopes.
    'mcp_scope',
    // A role and a user in the translate README's setup example.
    'mcp_operator',
    'mcp_service',
    // The made-up tool of the "write your own tool" example.
    'mcp_my_tool',
    // In the code: the role of the remote endpoint's own user, and the
    // base id of the Drupal AI function calls.
    'mcp_tools_remote_executor',
    'mcp_tools_tool',
  ];

  /**
   * The module's folder.
   */
  private function root(): string {
    return dirname(__DIR__, 4);
  }

  /**
   * The ids of the tools, read from the plugin files.
   *
   * @return string[]
   *   The tool ids.
   */
  private function toolIds(): array {
    $ids = [];
    $files = array_merge(
      glob($this->root() . '/src/Plugin/tool/Tool/*.php') ?: [],
      glob($this->root() . '/modules/*/src/Plugin/tool/Tool/*.php') ?: [],
    );
    foreach ($files as $file) {
      if (preg_match("/^\s*id: '([a-z0-9_]+)'/m", (string) file_get_contents($file), $found)) {
        $ids[] = $found[1];
      }
    }
    return $ids;
  }

  /**
   * What is known not to be a tool, by name.
   *
   * @return array<string, int>
   *   Tool ids, module names and the other known names, as keys.
   */
  private function known(): array {
    $modules = array_map('basename', glob($this->root() . '/modules/*', GLOB_ONLYDIR) ?: []);
    return array_flip(array_merge(
      $this->toolIds(),
      $modules,
      self::NOT_TOOLS,
      ['mcp_tools', 'mcp_tools_discover_tools', 'mcp_tools_get_tool_info', 'mcp_tools_execute_tool'],
    ));
  }

  /**
   * A message of the code that names a tool names one that exists.
   *
   * "User not found. Use mcp_list_users to locate users." There is no such
   * tool: it is mcp_tools_get_users. A model that is told a name calls it,
   * so 21 refusals sent it to "Unknown tool". This reads every sentence in
   * a string of the code (a string with a space in it) for such names.
   */
  public function testMessagesNameRealTools(): void {
    $known = $this->known();
    $files = [];
    foreach ([$this->root() . '/src', ...(glob($this->root() . '/modules/*/src', GLOB_ONLYDIR) ?: [])] as $folder) {
      $all = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS));
      foreach ($all as $file) {
        if ($file->getExtension() === 'php') {
          $files[] = $file->getPathname();
        }
      }
    }
    $this->assertGreaterThan(300, count($files));

    $wrong = [];
    foreach ($files as $file) {
      // The strings of the file, as PHP itself reads them: a quoted string,
      // or the text between the variables of one.
      $strings = [];
      foreach (token_get_all((string) file_get_contents($file)) as $token) {
        if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], TRUE)) {
          $strings[] = $token[1];
        }
      }
      foreach ($strings as $string) {
        if (!str_contains($string, ' ')) {
          continue;
        }
        // Not a table in a query, a variable, a service, a config name, a
        // permission or a path.
        preg_match_all('/(?<![{$>\w-])mcp_[a-z0-9_]*[a-z0-9]\b(?![.:\/\w}-])/', $string, $found);
        foreach (array_unique($found[0]) as $name) {
          if (!isset($known[$name])) {
            $wrong[] = substr($file, strlen($this->root()) + 1) . ': ' . $name;
          }
        }
      }
    }
    $wrong = array_values(array_unique($wrong));

    $this->assertSame([], $wrong, "These messages name tools that do not exist:\n  " . implode("\n  ", $wrong));
  }

  /**
   * Every name in the docs that looks like a tool is a tool.
   */
  public function testDocsNameRealTools(): void {
    $docs = array_merge(
      [$this->root() . '/README.md'],
      glob($this->root() . '/docs/*.md') ?: [],
      glob($this->root() . '/modules/*/README.md') ?: [],
    );
    $docs = array_filter($docs, 'is_file');
    if ($docs === []) {
      $this->markTestSkipped('This copy of the module has no docs (a release archive).');
    }

    $this->assertGreaterThan(200, count($this->toolIds()), 'The tool ids were not found in the plugin files.');
    $known = $this->known();

    $wrong = [];
    foreach ($docs as $doc) {
      // A name followed by a dot, a colon or a slash is a service, a config
      // name, a permission or a path, not a tool.
      preg_match_all('/\bmcp_[a-z0-9_]*[a-z0-9]\b(?![.:\/\w-])/', (string) file_get_contents($doc), $found);
      foreach (array_unique($found[0]) as $name) {
        if (!isset($known[$name])) {
          $wrong[] = substr($doc, strlen($this->root()) + 1) . ': ' . $name;
        }
      }
    }

    $this->assertSame([], $wrong, "These docs name tools that do not exist:\n  " . implode("\n  ", $wrong));
  }

}
