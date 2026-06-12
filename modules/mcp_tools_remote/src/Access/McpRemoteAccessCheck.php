<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Route-level access check for the MCP remote endpoint.
 *
 * Replaces `_access: TRUE` so the route is gated by the framework rather
 * than relying solely on controller logic (#3587523). Scope is deliberately
 * minimal: only the module's enabled flag is enforced here, thrown as a 404
 * so a disabled endpoint is indistinguishable from a nonexistent one.
 *
 * Credential and IP/origin validation intentionally stay in the controller:
 * its response contract conceals the endpoint (404) from non-allowlisted
 * clients BEFORE evaluating credentials, and answers 401 only to allowlisted
 * clients. A route-level credential presence check would invert that order
 * and leak the endpoint's existence as a 403.
 */
final class McpRemoteAccessCheck implements AccessInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Checks access for the MCP remote endpoint route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   When the remote server is disabled — 404, never 403, so the disabled
   *   endpoint stays concealed.
   */
  public function access(): AccessResultInterface {
    $remoteConfig = $this->configFactory->get('mcp_tools_remote.settings');
    if (!$remoteConfig->get('enabled')) {
      throw new NotFoundHttpException();
    }

    // Allowed at the routing layer; the controller performs the full
    // security pipeline (IP/origin allowlist, key validation, scopes).
    return AccessResult::allowed()
      ->addCacheableDependency($remoteConfig)
      ->setCacheMaxAge(0);
  }

}
