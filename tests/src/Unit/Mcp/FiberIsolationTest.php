<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\mcp_tools\Mcp\FiberIsolation;
use Drupal\Tests\UnitTestCase;

/**
 * Drupal's Fiber suspensions stay inside; the SDK's pass through.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\FiberIsolation::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class FiberIsolationTest extends UnitTestCase {

  /**
   * Outside a Fiber the work simply runs.
   */
  public function testRunsWorkDirectlyOutsideAFiber(): void {
    $this->assertSame('done', FiberIsolation::run(static fn(): string => 'done'));
  }

  /**
   * Only suspensions meant for the SDK reach the code that drives the Fiber.
   */
  public function testOnlySdkSuspensionsReachTheDriver(): void {
    $work = static function (): array {
      // What Drupal core does to let a caller batch work.
      \Fiber::suspend();
      \Fiber::suspend('resume me');
      // What the SDK's client gateway does to reach the client.
      $answer = \Fiber::suspend(['type' => 'notification', 'notification' => 'ping']);
      \Fiber::suspend();
      return ['answer' => $answer];
    };

    // Play the SDK: run the handler in a Fiber and record what it yields.
    $fiber = new \Fiber(static fn(): mixed => FiberIsolation::run($work));
    $seen = [];
    $value = $fiber->start();
    while (!$fiber->isTerminated()) {
      $seen[] = $value;
      $value = $fiber->resume('pong');
    }

    $this->assertSame([['type' => 'notification', 'notification' => 'ping']], $seen);
    $this->assertSame(['answer' => 'pong'], $fiber->getReturn(), 'The answer to the SDK suspension reached the work.');
  }

  /**
   * What the work throws comes out as it is.
   */
  public function testExceptionsPassThrough(): void {
    $fiber = new \Fiber(static fn(): mixed => FiberIsolation::run(static function (): void {
      \Fiber::suspend();
      throw new \RuntimeException('boom');
    }));
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('boom');
    $fiber->start();
  }

}
