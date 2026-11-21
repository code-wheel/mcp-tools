<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Mcp\Server\Suspension\NotificationSuspension;
use Mcp\Server\Suspension\RequestSuspension;

/**
 * Keeps Drupal's Fiber suspensions away from the SDK.
 *
 * The SDK runs every request handler in a Fiber and expects each suspension
 * to be one of its own: a request or a notification for the client. Drupal
 * core also suspends the current Fiber (entity loads, path alias lookups,
 * rendering) so that a caller can batch work. Up to mcp/sdk 0.9 the SDK
 * logged each of those as "Fiber yielded unexpected payload" and resumed;
 * from 0.10 it fails the request. Work that may load entities or render
 * therefore runs in a Fiber of its own here: Drupal's suspensions are resumed
 * on the spot, the SDK's are passed up and their answers passed back down.
 */
final class FiberIsolation {

  /**
   * Runs work that Drupal may suspend, from inside an SDK request handler.
   *
   * @param callable $work
   *   The work. Its return value is returned.
   *
   * @return mixed
   *   Whatever the work returns.
   */
  public static function run(callable $work): mixed {
    if (\Fiber::getCurrent() === NULL) {
      return $work();
    }

    $fiber = new \Fiber($work);
    $suspended = $fiber->start();
    while (!$fiber->isTerminated()) {
      $suspended = self::isSdkSuspension($suspended)
        ? $fiber->resume(\Fiber::suspend($suspended))
        : $fiber->resume();
    }
    return $fiber->getReturn();
  }

  /**
   * Whether a suspended value is addressed to the SDK's transport.
   *
   * Up to mcp/sdk 0.8 that is an array with a "type" key; from 0.9 it is a
   * RequestSuspension or NotificationSuspension object.
   */
  private static function isSdkSuspension(mixed $value): bool {
    return (is_array($value) && isset($value['type']))
      || $value instanceof RequestSuspension
      || $value instanceof NotificationSuspension;
  }

}
