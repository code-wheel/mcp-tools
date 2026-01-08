<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Controller;

use CodeWheel\McpSecurity\Validation\IpValidator;
use CodeWheel\McpSecurity\Validation\OriginValidator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\mcp_tools\Mcp\McpToolsServerFactory;
use Drupal\mcp_tools\Mcp\Error\ToolErrorHandlerInterface;
use Drupal\mcp_tools\Mcp\Prompt\PromptRegistry;
use Drupal\mcp_tools\Mcp\Resource\ResourceRegistry;
use Drupal\mcp_tools\Mcp\ServerConfigRepository;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_remote\Service\ApiKeyManager;
use GuzzleHttp\Psr7\HttpFactory;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP endpoint for MCP Tools remote transport.
 */
final class McpToolsRemoteController implements ContainerInjectionInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ApiKeyManager $apiKeyManager,
    private readonly AccessManager $accessManager,
    private readonly PluginManagerInterface $toolManager,
    private readonly ResourceRegistry $resourceRegistry,
    private readonly PromptRegistry $promptRegistry,
    private readonly ServerConfigRepository $serverConfigRepository,
    private readonly ToolErrorHandlerInterface $toolErrorHandler,
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly AccountSwitcherInterface $accountSwitcher,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly LoggerInterface $logger,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('mcp_tools_remote.api_key_manager'),
      $container->get('mcp_tools.access_manager'),
      $container->get('plugin.manager.tool'),
      $container->get('mcp_tools.resource_registry'),
      $container->get('mcp_tools.prompt_registry'),
      $container->get('mcp_tools.server_config_repository'),
      $container->get('mcp_tools.tool_error_handler'),
      $container->get('entity_type.manager'),
      $container->get('account_switcher'),
      $container->get('event_dispatcher'),
      $container->get('logger.channel.mcp_tools_remote'),
    );
  }

  /**
   * Handles MCP requests over HTTP using Streamable HTTP transport.
   */
  public function handle(Request $request): Response {
    $remoteConfig = $this->configFactory->get('mcp_tools_remote.settings');
    if (!$remoteConfig->get('enabled')) {
      return new Response('Not found.', 404);
    }

    // Optional IP allowlist (defense-in-depth for the remote endpoint).
    $allowedIps = $remoteConfig->get('allowed_ips') ?? [];
    if (is_array($allowedIps)) {
      $allowedIps = array_values(array_filter(array_map('trim', $allowedIps)));
    }
    else {
      $allowedIps = [];
    }

    if (!empty($allowedIps)) {
      $ipValidator = new IpValidator($allowedIps);
      $clientIp = $request->getClientIp();
      if (!$clientIp || !$ipValidator->isAllowed($clientIp)) {
        return new Response('Not found.', 404);
      }
    }

    // Optional origin allowlist (DNS rebinding defense-in-depth).
    $allowedOrigins = $remoteConfig->get('allowed_origins') ?? [];
    if (is_array($allowedOrigins)) {
      $allowedOrigins = array_values(array_filter(array_map('trim', $allowedOrigins)));
    }
    else {
      $allowedOrigins = [];
    }

    $originCheck = $this->validateOrigin($request, $allowedOrigins);
    if ($originCheck) {
      return $originCheck;
    }

    if (!class_exists(\Mcp\Server::class)) {
      return new Response('Missing dependency: mcp/sdk', 500);
    }

    $acceptCheck = $this->validateAcceptHeader($request);
    if ($acceptCheck) {
      return $acceptCheck;
    }

    $apiKey = $this->extractApiKey($request);
    $key = $apiKey ? $this->apiKeyManager->validate($apiKey) : NULL;
    if (!$key) {
      $response = new JsonResponse(['error' => 'Authentication required'], 401);
      $response->headers->set('WWW-Authenticate', 'Bearer realm="mcp_tools_remote"');
      return $response;
    }

    $serverId = trim((string) ($remoteConfig->get('server_id') ?? ''));
    $serverConfig = $serverId !== '' ? $this->serverConfigRepository->getServer($serverId) : NULL;
    if ($serverId !== '' && !$serverConfig) {
      return new Response('Configured MCP server profile not found: ' . $serverId, 500);
    }

    if ($serverConfig) {
      $access = $this->serverConfigRepository->checkAccess($serverConfig, $request);
      if (!$access['allowed']) {
        return new Response($access['message'] ?? 'Access denied.', 403);
      }

      if (!$this->serverConfigRepository->allowsTransport($serverConfig, 'http')) {
        return new Response('Server profile does not allow HTTP transport.', 403);
      }
    }

    // Provide a trusted rate-limit client identifier derived from the API key.
    // This avoids relying on client-supplied headers for per-key throttling.
    if (!empty($key['key_id']) && is_string($key['key_id'])) {
      $request->attributes->set('mcp_tools.client_id', 'remote_key:' . $key['key_id']);
    }

    // Resolve scopes from key, intersected with MCP Tools allowed scopes.
    $allowedScopes = $this->configFactory->get('mcp_tools.settings')->get('access.allowed_scopes') ?? ['read'];
    $scopes = array_values(array_intersect($key['scopes'] ?? ['read'], $allowedScopes));
    if (empty($scopes)) {
      $scopes = ['read'];
    }

    if ($serverConfig && !empty($serverConfig['scopes'])) {
      $scopes = array_values(array_intersect($scopes, (array) $serverConfig['scopes']));
      if (empty($scopes)) {
        return new Response('Access denied: no permitted scopes for this server profile.', 403);
      }
    }

    // Force scopes for this request (do not trust client-provided scope headers).
    $this->accessManager->setScopes($scopes);

    // Execute as configured user for consistent attribution.
    $uid = (int) ($remoteConfig->get('uid') ?? 0);
    if ($uid === 0) {
      return new Response('Execution user not configured. Visit /admin/config/services/mcp-tools/remote to set up.', 500);
    }
    $allowUid1 = (bool) $remoteConfig->get('allow_uid1');
    if ($uid === 1 && !$allowUid1) {
      return new Response('Execution user uid 1 is not allowed. Enable "Use site admin (uid 1)" in remote settings to override.', 500);
    }
    $account = $this->entityTypeManagerService->getStorage('user')->load($uid);
    if (!$account) {
      return new Response('Configured execution user (uid ' . $uid . ') not found.', 500);
    }

    $this->accountSwitcher->switchTo($account);
    try {
      $schemaConverter = new ToolApiSchemaConverter();
      $serverFactory = new McpToolsServerFactory(
        $this->toolManager,
        $schemaConverter,
        $this->logger,
        $this->eventDispatcher,
        $this->resourceRegistry,
        $this->promptRegistry,
        $this->toolErrorHandler,
      );

      $serverName = $serverConfig
        ? (string) ($serverConfig['name'] ?? 'Drupal MCP Tools')
        : (string) ($remoteConfig->get('server_name') ?? 'Drupal MCP Tools');
      $serverVersion = $serverConfig
        ? (string) ($serverConfig['version'] ?? '1.0.0')
        : (string) ($remoteConfig->get('server_version') ?? '1.0.0');
      $paginationLimit = $serverConfig
        ? (int) ($serverConfig['pagination_limit'] ?? 50)
        : (int) ($remoteConfig->get('pagination_limit') ?? 50);
      $includeAllTools = $serverConfig
        ? (bool) ($serverConfig['include_all_tools'] ?? FALSE)
        : (bool) ($remoteConfig->get('include_all_tools') ?? FALSE);
      $gatewayMode = $serverConfig
        ? (bool) ($serverConfig['gateway_mode'] ?? FALSE)
        : (bool) ($remoteConfig->get('gateway_mode') ?? FALSE);
      $enableResources = $serverConfig
        ? (bool) ($serverConfig['enable_resources'] ?? TRUE)
        : TRUE;
      $enablePrompts = $serverConfig
        ? (bool) ($serverConfig['enable_prompts'] ?? TRUE)
        : TRUE;

      $server = $serverFactory->create(
        $serverName,
        $serverVersion,
        $paginationLimit,
        $includeAllTools,
        new FileSessionStore(
          rtrim(sys_get_temp_dir(), \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . 'mcp_tools_remote_sessions_' . substr(hash('sha256', \Drupal::root()), 0, 12),
          3600,
        ),
        3600,
        $gatewayMode,
        $enableResources,
        $enablePrompts,
      );

      $httpFactory = new HttpFactory();
      $psrHttpFactory = new PsrHttpFactory($httpFactory, $httpFactory, $httpFactory, $httpFactory);
      $psrRequest = $psrHttpFactory->createRequest($request);

      $transport = new StreamableHttpTransport(
        $psrRequest,
        $httpFactory,
        $httpFactory,
        logger: $this->logger,
      );

      $psrResponse = $server->run($transport);

      $streamed = !$psrResponse->getBody()->isSeekable();
      $foundationFactory = new HttpFoundationFactory();
      $response = $foundationFactory->createResponse($psrResponse, $streamed);
      $response->headers->set('Cache-Control', 'no-store');
      return $response;
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

  private function extractApiKey(Request $request): ?string {
    $auth = (string) $request->headers->get('Authorization', '');
    if (str_starts_with($auth, 'Bearer ')) {
      return trim(substr($auth, 7));
    }

    $headerKey = (string) $request->headers->get('X-MCP-Api-Key', '');
    return $headerKey !== '' ? trim($headerKey) : NULL;
  }

  /**
   * Extract a hostname for allowlist checks.
   *
   * Uses Origin, then Referer, then Host (non-browser clients generally won't
   * send Origin/Referer).
   */
  private function extractHostname(Request $request): ?string {
    $origin = (string) $request->headers->get('Origin', '');
    $referer = (string) $request->headers->get('Referer', '');

    $candidate = '';
    if ($origin !== '') {
      $candidate = $origin;
    }
    elseif ($referer !== '') {
      $candidate = $referer;
    }

    if ($candidate === '') {
      $host = $request->getHost();
      return $host !== '' ? $host : NULL;
    }

    // Use the package's hostname extraction.
    return (new OriginValidator([]))->extractHostname($candidate);
  }

  /**
   * Validate the Origin header per MCP Streamable HTTP guidance.
   */
  private function validateOrigin(Request $request, array $allowedOrigins): ?Response {
    $originHeader = trim((string) $request->headers->get('Origin', ''));
    $requestHost = $request->getHost();

    if ($originHeader !== '') {
      $originHost = $this->extractHostnameFromHeader($originHeader);
      if (!$originHost) {
        return new Response('Not found.', 404);
      }

      if (!empty($allowedOrigins)) {
        $originValidator = new OriginValidator($allowedOrigins);
        if (!$originValidator->isAllowed($originHost)) {
          return new Response('Not found.', 404);
        }
      }
      else {
        if ($requestHost === '' || strcasecmp($originHost, $requestHost) !== 0) {
          return new Response('Not found.', 404);
        }
      }

      return NULL;
    }

    if (!empty($allowedOrigins)) {
      $hostname = $this->extractHostname($request);
      if (!$hostname) {
        return new Response('Not found.', 404);
      }
      $originValidator = new OriginValidator($allowedOrigins);
      if (!$originValidator->isAllowed($hostname)) {
        return new Response('Not found.', 404);
      }
    }

    return NULL;
  }

  /**
   * Ensure clients advertise required Accept types for Streamable HTTP.
   */
  private function validateAcceptHeader(Request $request): ?Response {
    $method = strtoupper($request->getMethod());
    if ($method !== 'GET' && $method !== 'POST') {
      return NULL;
    }

    $accept = strtolower((string) $request->headers->get('Accept', ''));
    if ($method === 'POST') {
      if (!str_contains($accept, 'application/json') || !str_contains($accept, 'text/event-stream')) {
        return new Response('Not acceptable.', 406);
      }
      return NULL;
    }

    if ($method === 'GET') {
      if (!str_contains($accept, 'text/event-stream')) {
        return new Response('Not acceptable.', 406);
      }
    }

    return NULL;
  }

  private function extractHostnameFromHeader(string $value): ?string {
    return (new OriginValidator([]))->extractHostname($value);
  }

}
