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

    if (!empty($allowedOrigins)) {
      $originValidator = new OriginValidator($allowedOrigins);
      $hostname = $this->extractHostname($request);
      if (!$hostname || !$originValidator->isAllowed($hostname)) {
        return new Response('Not found.', 404);
      }
    }

    if (!class_exists(\Mcp\Server::class)) {
      return new Response('Missing dependency: mcp/sdk', 500);
    }

    $apiKey = $this->extractApiKey($request);
    $key = $apiKey ? $this->apiKeyManager->validate($apiKey) : NULL;
    if (!$key) {
      $response = new JsonResponse(['error' => 'Authentication required'], 401);
      $response->headers->set('WWW-Authenticate', 'Bearer realm="mcp_tools_remote"');
      return $response;
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

    // Force scopes for this request (do not trust client-provided scope headers).
    $this->accessManager->setScopes($scopes);

    // Execute as configured user for consistent attribution.
    $uid = (int) ($remoteConfig->get('uid') ?? 1);
    if ($uid === 1) {
      return new Response('Invalid execution user.', 500);
    }
    $account = $this->entityTypeManagerService->getStorage('user')->load($uid);
    if (!$account) {
      return new Response('Invalid execution user.', 500);
    }

    $this->accountSwitcher->switchTo($account);
    try {
      $schemaConverter = new ToolApiSchemaConverter();
      $serverFactory = new McpToolsServerFactory(
        $this->toolManager,
        $schemaConverter,
        $this->logger,
        $this->eventDispatcher,
      );

      $server = $serverFactory->create(
        (string) ($remoteConfig->get('server_name') ?? 'Drupal MCP Tools'),
        (string) ($remoteConfig->get('server_version') ?? '1.0.0'),
        (int) ($remoteConfig->get('pagination_limit') ?? 50),
        (bool) ($remoteConfig->get('include_all_tools') ?? FALSE),
        new FileSessionStore(
          rtrim(sys_get_temp_dir(), \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . 'mcp_tools_remote_sessions_' . substr(hash('sha256', \Drupal::root()), 0, 12),
          3600,
        ),
        3600,
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
    return OriginValidator::extractHostname($candidate);
  }

}
