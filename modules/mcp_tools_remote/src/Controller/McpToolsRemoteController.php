<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Controller;

use Mcp\Server;
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

  /**
   * {@inheritdoc}
   */
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

    // Security validations (IP, Origin, Accept header).
    $securityCheck = $this->performSecurityChecks($request, $remoteConfig);
    if ($securityCheck) {
      return $securityCheck;
    }

    if (!class_exists(Server::class)) {
      return new Response('Missing dependency: mcp/sdk', 500);
    }

    // API key authentication.
    $apiKey = $this->extractApiKey($request);
    $key = $apiKey ? $this->apiKeyManager->validate($apiKey) : NULL;
    if (!$key) {
      $response = new JsonResponse(['error' => 'Authentication required'], 401);
      $response->headers->set('WWW-Authenticate', 'Bearer realm="mcp_tools_remote"');
      return $response;
    }

    // Server profile validation.
    $serverConfig = $this->loadServerConfig($remoteConfig);
    if ($serverConfig instanceof Response) {
      return $serverConfig;
    }

    if ($serverConfig) {
      $accessCheck = $this->checkServerAccess($serverConfig, $request);
      if ($accessCheck) {
        return $accessCheck;
      }
    }

    // Set rate-limit client ID from API key.
    if (!empty($key['key_id']) && is_string($key['key_id'])) {
      $request->attributes->set('mcp_tools.client_id', 'remote_key:' . $key['key_id']);
    }

    // Resolve and set scopes.
    $scopeResult = $this->resolveScopes($key, $serverConfig);
    if ($scopeResult instanceof Response) {
      return $scopeResult;
    }
    $this->accessManager->setScopes($scopeResult);

    // Resolve execution user.
    $accountResult = $this->resolveExecutionAccount($remoteConfig);
    if ($accountResult instanceof Response) {
      return $accountResult;
    }

    // Execute request as configured user.
    $this->accountSwitcher->switchTo($accountResult);
    try {
      return $this->executeRequest($request, $remoteConfig, $serverConfig);
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * Performs IP, Origin, and Accept header security checks.
   */
  private function performSecurityChecks(Request $request, $remoteConfig): ?Response {
    // IP allowlist check.
    $allowedIps = $this->normalizeConfigList($remoteConfig->get('allowed_ips'));
    if (!empty($allowedIps)) {
      $ipValidator = new IpValidator($allowedIps);
      $clientIp = $request->getClientIp();
      if (!$clientIp || !$ipValidator->isAllowed($clientIp)) {
        return new Response('Not found.', 404);
      }
    }

    // Origin allowlist check.
    $allowedOrigins = $this->normalizeConfigList($remoteConfig->get('allowed_origins'));
    $originCheck = $this->validateOrigin($request, $allowedOrigins);
    if ($originCheck) {
      return $originCheck;
    }

    // Accept header check.
    return $this->validateAcceptHeader($request);
  }

  /**
   * Loads the server config if specified.
   *
   * @return array|Response|null
   *   Server config array, error Response, or NULL if no profile configured.
   */
  private function loadServerConfig($remoteConfig): array|Response|null {
    $serverId = trim((string) ($remoteConfig->get('server_id') ?? ''));
    if ($serverId === '') {
      return NULL;
    }

    $serverConfig = $this->serverConfigRepository->getServer($serverId);
    if (!$serverConfig) {
      return new Response('Configured MCP server profile not found: ' . $serverId, 500);
    }

    return $serverConfig;
  }

  /**
   * Checks server profile access and transport permissions.
   */
  private function checkServerAccess(array $serverConfig, Request $request): ?Response {
    $access = $this->serverConfigRepository->checkAccess($serverConfig, $request);
    if (!$access['allowed']) {
      return new Response($access['message'] ?? 'Access denied.', 403);
    }

    if (!$this->serverConfigRepository->allowsTransport($serverConfig, 'http')) {
      return new Response('Server profile does not allow HTTP transport.', 403);
    }

    return NULL;
  }

  /**
   * Resolves effective scopes from key and server config.
   *
   * @return array|Response
   *   Array of scopes or error Response.
   */
  private function resolveScopes(array $key, ?array $serverConfig): array|Response {
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

    return $scopes;
  }

  /**
   * Resolves and validates the execution user account.
   *
   * @return \Drupal\user\UserInterface|Response
   *   User account or error Response.
   */
  private function resolveExecutionAccount($remoteConfig): mixed {
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

    return $account;
  }

  /**
   * Executes the MCP request via the transport.
   */
  private function executeRequest(Request $request, $remoteConfig, ?array $serverConfig): Response {
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

    $serverParams = $this->resolveServerParams($remoteConfig, $serverConfig);

    $server = $serverFactory->create(
      $serverParams['name'],
      $serverParams['version'],
      $serverParams['pagination_limit'],
      $serverParams['include_all_tools'],
      new FileSessionStore(
        // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
        rtrim(sys_get_temp_dir(), \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . 'mcp_tools_remote_sessions_' . substr(hash('sha256', \Drupal::root()), 0, 12),
        3600,
      ),
      3600,
      $serverParams['gateway_mode'],
      $serverParams['enable_resources'],
      $serverParams['enable_prompts'],
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

  /**
   * Resolves server parameters from config and server profile.
   */
  private function resolveServerParams($remoteConfig, ?array $serverConfig): array {
    return [
      'name' => $serverConfig
        ? (string) ($serverConfig['name'] ?? 'Drupal MCP Tools')
        : (string) ($remoteConfig->get('server_name') ?? 'Drupal MCP Tools'),
      'version' => $serverConfig
        ? (string) ($serverConfig['version'] ?? '1.0.0')
        : (string) ($remoteConfig->get('server_version') ?? '1.0.0'),
      'pagination_limit' => $serverConfig
        ? (int) ($serverConfig['pagination_limit'] ?? 50)
        : (int) ($remoteConfig->get('pagination_limit') ?? 50),
      'include_all_tools' => $serverConfig
        ? (bool) ($serverConfig['include_all_tools'] ?? FALSE)
        : (bool) ($remoteConfig->get('include_all_tools') ?? FALSE),
      'gateway_mode' => $serverConfig
        ? (bool) ($serverConfig['gateway_mode'] ?? FALSE)
        : (bool) ($remoteConfig->get('gateway_mode') ?? FALSE),
      'enable_resources' => $serverConfig
        ? (bool) ($serverConfig['enable_resources'] ?? TRUE)
        : TRUE,
      'enable_prompts' => $serverConfig
        ? (bool) ($serverConfig['enable_prompts'] ?? TRUE)
        : TRUE,
    ];
  }

  /**
   * Normalizes a config value to a list of trimmed strings.
   */
  private function normalizeConfigList(mixed $value): array {
    if (!is_array($value)) {
      return [];
    }
    return array_values(array_filter(array_map('trim', $value)));
  }

  /**
   * Extract Api Key.
   */
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

  /**
   * Extract Hostname From Header.
   */
  private function extractHostnameFromHeader(string $value): ?string {
    return (new OriginValidator([]))->extractHostname($value);
  }

}
