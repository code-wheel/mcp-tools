<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Traits;

use Mcp\Server\Suspension\NotificationSuspension;
use Mcp\Server\Suspension\RequestSuspension;

/**
 * Reads what the MCP SDK hands to its transport when a handler suspends.
 *
 * A handler that asks the client something (elicitation, sampling) or sends
 * it a notification (progress) suspends its Fiber. Up to mcp/sdk 0.8 the
 * suspended value is an array with a "type" key; from 0.9 it is a
 * RequestSuspension or NotificationSuspension object. Tests that play the
 * transport's part read it through these methods.
 */
trait SdkSuspensionTrait {

  /**
   * The request the handler wants answered, if this is one.
   */
  protected function suspendedRequest(mixed $value): ?object {
    if ($value instanceof RequestSuspension) {
      return $value->request;
    }
    if (is_array($value) && ($value['type'] ?? '') === 'request') {
      return $value['request'];
    }
    return NULL;
  }

  /**
   * The notification the handler wants delivered, if this is one.
   */
  protected function suspendedNotification(mixed $value): ?object {
    if ($value instanceof NotificationSuspension) {
      return $value->notification;
    }
    if (is_array($value) && ($value['type'] ?? '') === 'notification') {
      return $value['notification'];
    }
    return NULL;
  }

}
