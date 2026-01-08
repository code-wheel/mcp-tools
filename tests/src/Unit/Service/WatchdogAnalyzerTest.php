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

}
