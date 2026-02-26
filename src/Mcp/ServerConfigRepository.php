<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads MCP server profiles from configuration.
 *
 * Note: This class injects ContainerInterface to resolve dynamic
 * permission callback references from configuration (e.g.,
 * 'mcp_tools.access_manager:checkAccess'). This is intentional -
 * the service locator pattern is necessary here because callback
 * services are configured dynamically and can't be known at
 * compile time. This is similar to how Drupal's plugin managers
 * resolve tagged services.
 */
class ServerConfigRepository {

  private const DEFAULT_SERVER_ID = 'default';

  private const DEFAULTS = [
    'name' => 'Drupal MCP Tools',
    'version' => '1.0.0',
    'pagination_limit' => 50,
    'include_all_tools' => FALSE,
    'gateway_mode' => FALSE,
    'enable_resources' => TRUE,
    'enable_prompts' => TRUE,
    'component_public_only' => FALSE,
    'transports' => [],
    'enabled' => TRUE,
    'scopes' => [],
    'permission_callback' => NULL,
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ContainerInterface $container,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Returns all configured server profiles.
   *
   * @return array<string, array<string, mixed>>
   *   Server definitions keyed by server ID.
   */
  public function getServers(): array {
    $config = $this->configFactory->get('mcp_tools_servers.settings');
    $servers = $config->get('servers') ?? [];

    if (!is_array($servers)) {
      $servers = [];
    }

    if (empty($servers)) {
      $servers[self::DEFAULT_SERVER_ID] = [];
    }

    $normalized = [];
    foreach ($servers as $serverId => $server) {
      if (!is_array($server)) {
        $server = [];
      }
      $normalized[$serverId] = $this->applyDefaults((string) $serverId, $server);
    }

    $this->moduleHandler->alter('mcp_tools_server_configs', $normalized);

    foreach ($normalized as $serverId => $server) {
      if (!($server['enabled'] ?? TRUE)) {
        unset($normalized[$serverId]);
      }
    }

    return $normalized;
  }

  /**
   * Returns a single server profile, or NULL if not found.
   */
  public function getServer(?string $serverId): ?array {
    $servers = $this->getServers();

    if ($serverId === NULL || $serverId === '') {
      $serverId = $this->getDefaultServerId($servers);
    }

    return $servers[$serverId] ?? NULL;
  }

  /**
   * Returns the configured default server ID.
   *
   * @param array<string, array<string, mixed>> $servers
   *   Available servers.
   */
  public function getDefaultServerId(array $servers): string {
    $config = $this->configFactory->get('mcp_tools_servers.settings');
    $default = (string) ($config->get('default_server') ?? self::DEFAULT_SERVER_ID);

    if (isset($servers[$default])) {
      return $default;
    }

    $first = array_key_first($servers);
    return $first ?? self::DEFAULT_SERVER_ID;
  }

  /**
   * Evaluate the server permission callback, if configured.
   *
   * @return array{allowed: bool, message: string|null}
   *   Access decision and optional message.
   */
  public function checkAccess(array $serverConfig, ?Request $request): array {
    $callback = $this->resolvePermissionCallback($serverConfig['permission_callback'] ?? NULL);
    if ($callback === NULL) {
      return ['allowed' => TRUE, 'message' => NULL];
    }

    try {
      $result = $this->invokePermissionCallback($callback, $request, $serverConfig);
    }
    catch (\Throwable $e) {
      $this->logger->error('Server permission callback failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      return ['allowed' => FALSE, 'message' => 'Access denied by server permission callback.'];
    }

    if ($result instanceof AccessResultInterface) {
      return [
        'allowed' => $result->isAllowed(),
        'message' => $result->isAllowed() ? NULL : 'Access denied by server permission callback.',
      ];
    }

    if (is_string($result)) {
      return ['allowed' => FALSE, 'message' => $result];
    }

    if (is_bool($result)) {
      return [
        'allowed' => $result,
        'message' => $result ? NULL : 'Access denied by server permission callback.',
      ];
    }

    return ['allowed' => FALSE, 'message' => 'Access denied by server permission callback.'];
  }

  /**
   * Check if a server profile allows a given transport.
   */
  public function allowsTransport(array $serverConfig, string $transport): bool {
    $transports = $serverConfig['transports'] ?? [];
    if (!is_array($transports) || $transports === []) {
      return TRUE;
    }

    return in_array(strtolower($transport), $transports, TRUE);
  }

  /**
   * Normalize and apply defaults to a server config.
   *
   * @param string $serverId
   *   The server ID.
   * @param array<string, mixed> $server
   *   The server configuration.
   *
   * @return array<string, mixed>
   *   The normalized server configuration.
   */
  private function applyDefaults(string $serverId, array $server): array {
    $merged = array_merge(self::DEFAULTS, $server);

    $merged['id'] = $serverId;
    $merged['pagination_limit'] = max(1, (int) $merged['pagination_limit']);
    $merged['include_all_tools'] = (bool) $merged['include_all_tools'];
    $merged['gateway_mode'] = (bool) $merged['gateway_mode'];
    $merged['enable_resources'] = (bool) $merged['enable_resources'];
    $merged['enable_prompts'] = (bool) $merged['enable_prompts'];
    $merged['component_public_only'] = (bool) $merged['component_public_only'];
    $merged['enabled'] = (bool) $merged['enabled'];
    $merged['scopes'] = $this->normalizeScopes($merged['scopes'] ?? []);
    $merged['transports'] = $this->normalizeTransports($merged['transports'] ?? []);

    return $merged;
  }

  /**
   * Normalize scope configuration.
   *
   * @param mixed $scopes
   *   The scopes.
   *
   * @return string[]
   *   The result.
   */
  private function normalizeScopes(mixed $scopes): array {
    if (is_string($scopes)) {
      $scopes = array_filter(array_map('trim', explode(',', $scopes)));
    }

    if (!is_array($scopes)) {
      return [];
    }

    $scopes = array_map(static fn($scope) => is_string($scope) ? strtolower($scope) : $scope, $scopes);

    return array_values(array_intersect($scopes, AccessManager::ALL_SCOPES));
  }

  /**
   * Normalize transport configuration.
   *
   * @param mixed $transports
   *   The transports.
   *
   * @return string[]
   *   The result.
   */
  private function normalizeTransports(mixed $transports): array {
    if (is_string($transports)) {
      $transports = array_filter(array_map('trim', explode(',', $transports)));
    }

    if (!is_array($transports)) {
      return [];
    }

    $allowed = ['http', 'stdio'];
    $transports = array_map(static fn($value) => is_string($value) ? strtolower($value) : $value, $transports);

    return array_values(array_intersect($transports, $allowed));
  }

  /**
   * Resolves a permission callback string into a callable.
   */
  private function resolvePermissionCallback(mixed $callback): ?callable {
    if ($callback === NULL || $callback === '') {
      return NULL;
    }

    // Handle closures and callable objects directly.
    if ($callback instanceof \Closure || (is_object($callback) && is_callable($callback))) {
      return $callback;
    }

    if (is_array($callback)) {
      if (count($callback) === 2 && is_string($callback[0]) && $this->container->has($callback[0])) {
        return [$this->container->get($callback[0]), $callback[1]];
      }
      return is_callable($callback) ? $callback : NULL;
    }

    if (!is_string($callback)) {
      return NULL;
    }

    if (str_contains($callback, '::') && is_callable($callback)) {
      return $callback;
    }

    if (str_contains($callback, ':')) {
      [$serviceId, $method] = explode(':', $callback, 2);
      if ($this->container->has($serviceId)) {
        $service = $this->container->get($serviceId);
        if (is_callable([$service, $method])) {
          return [$service, $method];
        }
      }
      $this->logger->warning('Invalid permission callback service for MCP server: @callback', [
        '@callback' => $callback,
      ]);
      return NULL;
    }

    if ($this->container->has($callback)) {
      $service = $this->container->get($callback);
      if (is_callable($service)) {
        return $service;
      }
      if (method_exists($service, '__invoke')) {
        return $service;
      }
    }

    if (is_callable($callback)) {
      return $callback;
    }

    $this->logger->warning('Invalid permission callback for MCP server: @callback', [
      '@callback' => $callback,
    ]);

    return NULL;
  }

  /**
   * Invoke a permission callback with supported arguments.
   *
   * @param callable $callback
   *   The callback.
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The current request.
   * @param array<string, mixed> $serverConfig
   *   The server configuration.
   */
  private function invokePermissionCallback(callable $callback, ?Request $request, array $serverConfig): mixed {
    $reflection = $this->getCallbackReflection($callback);
    $args = [];

    if ($reflection->getNumberOfParameters() >= 1) {
      $args[] = $request;
    }
    if ($reflection->getNumberOfParameters() >= 2) {
      $args[] = $serverConfig;
    }

    return $callback(...$args);
  }

  /**
   * Get reflection info for a callable.
   */
  private function getCallbackReflection(callable $callback): \ReflectionFunctionAbstract {
    if (is_array($callback)) {
      return new \ReflectionMethod($callback[0], $callback[1]);
    }

    if (is_string($callback) && str_contains($callback, '::')) {
      [$class, $method] = explode('::', $callback, 2);
      return new \ReflectionMethod($class, $method);
    }

    if ($callback instanceof \Closure) {
      return new \ReflectionFunction($callback);
    }

    return new \ReflectionFunction($callback);
  }

}
