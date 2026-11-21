<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\mcp_tools\Mcp\MinimumLevelLogger;
use Drupal\Tests\UnitTestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\MinimumLevelLogger::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class MinimumLevelLoggerTest extends UnitTestCase {

  private function recorder(): AbstractLogger {
    return new class() extends AbstractLogger {
      public array $records = [];

      public function log($level, string|\Stringable $message, array $context = []): void {
        $this->records[] = [$level, (string) $message, $context];
      }
    };
  }

  public function testDropsRecordsBelowWarningByDefault(): void {
    $recorder = $this->recorder();
    $logger = new MinimumLevelLogger($recorder);

    $logger->debug('Registered manual tool mcp_get_site_status from handler Closure');
    $logger->info('Received message to process.');
    $logger->notice('Session closed');
    $logger->warning('Fiber yielded unexpected payload.', ['session' => 'abc']);
    $logger->error('Transport error');

    $this->assertSame([
      [LogLevel::WARNING, 'Fiber yielded unexpected payload.', ['session' => 'abc']],
      [LogLevel::ERROR, 'Transport error', []],
    ], $recorder->records);
  }

  public function testHonoursAnotherMinimumLevel(): void {
    $recorder = $this->recorder();
    $logger = new MinimumLevelLogger($recorder, LogLevel::DEBUG);

    $logger->debug('Registered manual tool mcp_get_site_status from handler Closure');

    $this->assertCount(1, $recorder->records);
  }

  public function testPassesOnUnknownLevels(): void {
    $recorder = $this->recorder();
    $logger = new MinimumLevelLogger($recorder);

    $logger->log('custom', 'Not a PSR-3 level');

    $this->assertSame([['custom', 'Not a PSR-3 level', []]], $recorder->records);
  }

}
