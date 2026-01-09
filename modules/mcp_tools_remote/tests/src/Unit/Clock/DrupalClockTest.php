<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_remote\Unit\Clock;

use DateTimeImmutable;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\mcp_tools_remote\Clock\DrupalClock;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DrupalClock.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_remote\Clock\DrupalClock::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_remote')]
final class DrupalClockTest extends TestCase {

  public function testNowReturnsDateTimeImmutable(): void {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1704067200);

    $clock = new DrupalClock($time);
    $result = $clock->now();

    $this->assertInstanceOf(DateTimeImmutable::class, $result);
  }

  public function testNowUsesRequestTimestamp(): void {
    $expectedTimestamp = 1704067200;

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($expectedTimestamp);

    $clock = new DrupalClock($time);
    $result = $clock->now();

    $this->assertSame($expectedTimestamp, $result->getTimestamp());
  }

  public function testNowReturnsCurrentRequestTime(): void {
    $time = $this->createMock(TimeInterface::class);
    $time->expects($this->once())
      ->method('getRequestTime')
      ->willReturn(1704153600);

    $clock = new DrupalClock($time);
    $result = $clock->now();

    $this->assertSame('2024-01-02', $result->format('Y-m-d'));
  }

}
