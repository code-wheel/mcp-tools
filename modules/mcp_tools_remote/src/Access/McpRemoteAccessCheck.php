<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Route-level access check for the MCP remote endpoint.
 *
 * Replaces `_access: TRUE` with early gate checks so that requests never reach
 * the controller unless the module is enabled and a plausible credential is
 * present. The controller still performs full validation (key verification,
 * scope resolution, IP allowlist, origin check) as a second defense layer.
 */
final class McpRemoteAccessCheck implements AccessInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Checks access for the MCP remote endpoint route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route being checked.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, Request $request): AccessResultInterface {
    // Deny if the remote server is disabled in config.
    $remoteConfig = $this->configFactory->get('mcp_tools_remote.settings');
    if (!$remoteConfig->get('enabled')) {
      return AccessResult::forbidden('MCP remote server is disabled.')
        ->addCacheableDependency($remoteConfig);
    }

    // Deny if no API key / Bearer token is present in the request. This is a
    // presence check only — the controller validates the actual credential.
    if ($this->extractApiKey($request) === NULL) {
      return AccessResult::forbidden('Missing API key or Bearer token.')
        ->setCacheMaxAge(0);
    }

    // Allow — the controller performs full key validation, scope resolution,
    // IP allowlist, origin check, and account switching.
    return AccessResult::allowed()->setCacheMaxAge(0);
  }

  /**
   * Extracts an API key from Authorization or X-MCP-Api-Key headers.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request.
   *
   * @return string|null
   *   The raw API key string, or NULL if not present.
   */
  private function extractApiKey(Request $request): ?string {
    $auth = (string) $request->headers->get('Authorization', '');
    if (str_starts_with($auth, 'Bearer ')) {
      return trim(substr($auth, 7)) ?: NULL;
    }

    $headerKey = (string) $request->headers->get('X-MCP-Api-Key', '');
    return $headerKey !== '' ? trim($headerKey) : NULL;
  }

}
