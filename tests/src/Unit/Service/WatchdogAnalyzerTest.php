<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\mcp_tools\Service\WatchdogAnalyzer;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\WatchdogAnalyzer::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class WatchdogAnalyzerTest extends UnitTestCase {

  public function testFormatMessageSubstitutesVariables(): void {
    $service = new class($this->createMock(Connection::class)) extends WatchdogAnalyzer {

      public function format(string $message, ?string $variables): string {
        return $this->formatMessage($message, $variables);
      }

    };

    $message = 'Hello @name';
    $vars = serialize(['@name' => '<b>Alice</b>']);

    $this->assertSame('Hello Alice', $service->format($message, $vars));
  }

  public function testFormatMessageReturnsRawOnBadVariables(): void {
    $service = new class($this->createMock(Connection::class)) extends WatchdogAnalyzer {

      public function format(string $message, ?string $variables): string {
        return $this->formatMessage($message, $variables);
      }

    };

    $this->assertSame('Raw message', $service->format('Raw message', 'not-serialized'));
  }

  public function testFormatMessageSkipsOversizedSerializedVariables(): void {
    $service = new class($this->createMock(Connection::class)) extends WatchdogAnalyzer {

      public function format(string $message, ?string $variables): string {
        return $this->formatMessage($message, $variables);
      }

    };

    $large = serialize(['@name' => str_repeat('A', 70000)]);
    $this->assertSame('Hello @name', $service->format('Hello @name', $large));
  }

  /**
   * No backtrace, absolute path or secret goes to the model.
   */
  public function testFormatMessageLeavesOutBacktracesPathsAndSecrets(): void {
    $analyzer = new class($this->createMock(Connection::class)) extends WatchdogAnalyzer {

      public function format(string $message, ?string $variables): string {
        return $this->formatMessage($message, $variables);
      }

    };
    $root = defined('DRUPAL_ROOT') ? DRUPAL_ROOT : '/var/www/web';
    $out = $analyzer->format('%type: @message in %function (line %line of %file). @backtrace_string %password', serialize([
      '%type' => 'Error',
      '@message' => 'Boom',
      '%function' => 'f()',
      '%line' => 7,
      '%file' => $root . '/modules/custom/x/x.module',
      '@backtrace_string' => "#0 $root/core/index.php(1): f()",
      '%password' => 'hunter2',
    ]));
    $this->assertStringNotContainsString('#0', $out, 'A backtrace went to the model.');
    $this->assertStringNotContainsString($root . '/', $out, 'An absolute path went to the model.');
    $this->assertStringNotContainsString('hunter2', $out);
    $this->assertStringContainsString('modules/custom/x/x.module', $out);
  }

}
