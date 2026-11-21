<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\mcp_tools\Mcp\Sdk;
use PHPUnit\Framework\TestCase;

/**
 * Tests the mcp/sdk helper.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(Sdk::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class SdkTest extends TestCase {

  /**
   * The install hint names the newest SDK line composer.json allows.
   *
   * The hint used to say mcp/sdk:^0.2, the oldest line, long after newer
   * ones were supported. This fails when composer.json moves on and the
   * hint does not.
   */
  public function testInstallCommandNamesTheNewestAllowedSdkLine(): void {
    $root = dirname(__DIR__, 4);
    $composer = json_decode((string) file_get_contents($root . '/composer.json'), TRUE);
    $alternatives = array_map('trim', explode('||', $composer['require']['mcp/sdk']));
    $newest = end($alternatives);

    $this->assertSame('composer require mcp/sdk:' . $newest, Sdk::INSTALL_COMMAND);
  }

  /**
   * No message tells people to install an SDK line by hand any more.
   */
  public function testNoMessagePinsAnSdkVersionOfItsOwn(): void {
    $root = dirname(__DIR__, 4);
    $files = [
      'src/Commands/McpToolsCommands.php',
      'modules/mcp_tools_stdio/src/Commands/McpToolsStdioCommands.php',
      'modules/mcp_tools_stdio/mcp_tools_stdio.install',
      'modules/mcp_tools_remote/mcp_tools_remote.install',
    ];
    foreach ($files as $file) {
      $code = (string) file_get_contents($root . '/' . $file);
      $this->assertStringContainsString('Sdk::INSTALL_COMMAND', $code, $file);
      $this->assertDoesNotMatchRegularExpression('/composer require mcp\/sdk/', $code, $file);
    }
  }

  /**
   * The server class is found when the SDK is installed.
   */
  public function testServerClassIsFound(): void {
    $class = Sdk::serverClass();
    if ($class === NULL) {
      $this->markTestSkipped('mcp/sdk is not installed.');
    }
    $this->assertContains($class, ['Mcp\Server\Server', 'Mcp\Server']);
    $this->assertTrue(method_exists($class, 'builder'));
  }

}
