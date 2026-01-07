<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Clock;

use DateTimeImmutable;
use Drupal\Component\Datetime\TimeInterface;
use Psr\Clock\ClockInterface;

/**
 * Drupal Time API adapter for PSR-20 Clock.
 */
final class DrupalClock implements ClockInterface {

  public function __construct(
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function now(): DateTimeImmutable {
    return (new DateTimeImmutable())->setTimestamp($this->time->getRequestTime());
  }

}
