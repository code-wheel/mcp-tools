<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_remote\Unit\Controller;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\mcp_tools\Mcp\Error\ToolErrorHandlerInterface;
use Drupal\mcp_tools\Mcp\Prompt\PromptRegistry;
use Drupal\mcp_tools\Mcp\Resource\ResourceRegistry;
use Drupal\mcp_tools\Mcp\ServerConfigRepository;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_remote\Controller\McpToolsRemoteController;
use Drupal\mcp_tools_remote\Service\ApiKeyManager;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for McpToolsRemoteController.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_remote\Controller\McpToolsRemoteController::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_remote')]
final class McpToolsRemoteControllerTest extends UnitTestCase {

  private ConfigFactoryInterface $configFactory;
  private ApiKeyManager $apiKeyManager;
  private AccessManager $accessManager;
  private PluginManagerInterface $toolManager;
  private ResourceRegistry $resourceRegistry;
  private PromptRegistry $promptRegistry;
  private ServerConfigRepository $serverConfigRepository;
  private ToolErrorHandlerInterface $toolErrorHandler;
  private EntityTypeManagerInterface $entityTypeManager;
  private AccountSwitcherInterface $accountSwitcher;
  private EventDispatcherInterface $eventDispatcher;
  private LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->apiKeyManager = $this->createMock(ApiKeyManager::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->toolManager = $this->createMock(PluginManagerInterface::class);
    $this->resourceRegistry = $this->createMock(ResourceRegistry::class);
    $this->promptRegistry = $this->createMock(PromptRegistry::class);
    $this->serverConfigRepository = $this->createMock(ServerConfigRepository::class);
    $this->toolErrorHandler = $this->createMock(ToolErrorHandlerInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->accountSwitcher = $this->createMock(AccountSwitcherInterface::class);
    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    // Set up container with services and parameters for Drupal:: static calls.
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($this->logger);
    $container = new ContainerBuilder();
    $container->set('logger.factory', $loggerFactory);
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->setParameter('app.root', '/tmp');
    \Drupal::setContainer($container);
  }

  private function createController(?ConfigFactoryInterface $configFactory = NULL): McpToolsRemoteController {
    return new McpToolsRemoteController(
      $configFactory ?? $this->configFactory,
      $this->apiKeyManager,
      $this->accessManager,
      $this->toolManager,
      $this->resourceRegistry,
      $this->promptRegistry,
      $this->serverConfigRepository,
      $this->toolErrorHandler,
      $this->entityTypeManager,
      $this->accountSwitcher,
      $this->eventDispatcher,
      $this->logger,
    );
  }

  private function createRemoteConfig(array $values = []): ImmutableConfig {
    $defaults = [
      'enabled' => TRUE,
      'uid' => 5,
      'allow_uid1' => FALSE,
      'allowed_ips' => [],
      'allowed_origins' => [],
      'server_id' => '',
      'server_name' => 'Test Server',
      'server_version' => '1.0.0',
      'pagination_limit' => 50,
      'include_all_tools' => FALSE,
      'gateway_mode' => FALSE,
    ];
    $values = array_merge($defaults, $values);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(fn(string $key) => $values[$key] ?? NULL);
    return $config;
  }

  private function createMainConfig(array $values = []): ImmutableConfig {
    $defaults = [
      'access.allowed_scopes' => ['read', 'write'],
    ];
    $values = array_merge($defaults, $values);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(fn(string $key) => $values[$key] ?? NULL);
    return $config;
  }

  private function setupConfigFactory(ImmutableConfig $remoteConfig, ?ImmutableConfig $mainConfig = NULL): ConfigFactoryInterface {
    $mainConfig = $mainConfig ?? $this->createMainConfig();

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturnCallback(
      fn(string $name) => match ($name) {
        'mcp_tools_remote.settings' => $remoteConfig,
        'mcp_tools.settings' => $mainConfig,
        default => $this->createMock(ImmutableConfig::class),
      }
    );
    return $configFactory;
  }

  public function testHandleReturns404WhenDisabled(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => FALSE]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');

    $response = $controller->handle($request);

    $this->assertSame(404, $response->getStatusCode());
    $this->assertSame('Not found.', $response->getContent());
  }

  public function testHandleReturns401WhenNoApiKey(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => TRUE]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->method('validate')->willReturn(NULL);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    $this->assertSame(401, $response->getStatusCode());
    $this->assertSame('Bearer realm="mcp_tools_remote"', $response->headers->get('WWW-Authenticate'));
  }

  public function testHandleReturns401WhenInvalidApiKey(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => TRUE]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->method('validate')->with('invalid-key')->willReturn(NULL);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Authorization', 'Bearer invalid-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    $this->assertSame(401, $response->getStatusCode());
  }

  public function testHandleExtractsApiKeyFromBearerHeader(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => TRUE, 'uid' => 0]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->expects($this->once())
      ->method('validate')
      ->with('my-api-key')
      ->willReturn(['key_id' => 'test', 'scopes' => ['read']]);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Authorization', 'Bearer my-api-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    // Will fail at execution user check, but API key was validated.
    $response = $controller->handle($request);

    // 500 because uid=0 is not configured.
    $this->assertSame(500, $response->getStatusCode());
    $this->assertStringContainsString('Execution user not configured', $response->getContent());
  }

  public function testHandleExtractsApiKeyFromXMcpApiKeyHeader(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => TRUE, 'uid' => 0]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->expects($this->once())
      ->method('validate')
      ->with('header-api-key')
      ->willReturn(['key_id' => 'test', 'scopes' => ['read']]);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('X-MCP-Api-Key', 'header-api-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    // 500 because uid=0, but key was validated.
    $this->assertSame(500, $response->getStatusCode());
  }

  public function testHandleReturns404WhenIpNotAllowed(): void {
    $remoteConfig = $this->createRemoteConfig([
      'enabled' => TRUE,
      'allowed_ips' => ['192.168.1.0/24'],
    ]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST', [], [], [], [
      'REMOTE_ADDR' => '10.0.0.1',
    ]);
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    $this->assertSame(404, $response->getStatusCode());
  }

  public function testHandleAllowsRequestWhenIpInAllowlist(): void {
    $remoteConfig = $this->createRemoteConfig([
      'enabled' => TRUE,
      'allowed_ips' => ['192.168.1.0/24'],
      'uid' => 0,
    ]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->method('validate')->willReturn(['key_id' => 'test', 'scopes' => ['read']]);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST', [], [], [], [
      'REMOTE_ADDR' => '192.168.1.100',
    ]);
    $request->headers->set('Authorization', 'Bearer test-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    // Should pass IP check and fail at execution user (uid=0).
    $this->assertSame(500, $response->getStatusCode());
    $this->assertStringContainsString('Execution user not configured', $response->getContent());
  }

  public function testHandleReturns406WhenPostMissingAcceptHeaders(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => TRUE]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Accept', 'text/html');

    $response = $controller->handle($request);

    $this->assertSame(406, $response->getStatusCode());
    $this->assertSame('Not acceptable.', $response->getContent());
  }

  public function testHandleReturns406WhenGetMissingEventStreamAccept(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => TRUE]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = $controller->handle($request);

    $this->assertSame(406, $response->getStatusCode());
  }

  public function testHandleReturns500WhenExecutionUserNotConfigured(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => TRUE, 'uid' => 0]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->method('validate')->willReturn(['key_id' => 'test', 'scopes' => ['read']]);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Authorization', 'Bearer test-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    $this->assertSame(500, $response->getStatusCode());
    $this->assertStringContainsString('Execution user not configured', $response->getContent());
  }

  public function testHandleReturns500WhenUid1NotAllowed(): void {
    $remoteConfig = $this->createRemoteConfig([
      'enabled' => TRUE,
      'uid' => 1,
      'allow_uid1' => FALSE,
    ]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->method('validate')->willReturn(['key_id' => 'test', 'scopes' => ['read']]);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Authorization', 'Bearer test-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    $this->assertSame(500, $response->getStatusCode());
    $this->assertStringContainsString('uid 1 is not allowed', $response->getContent());
  }

  public function testHandleReturns500WhenExecutionUserNotFound(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => TRUE, 'uid' => 999]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->method('validate')->willReturn(['key_id' => 'test', 'scopes' => ['read']]);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(999)->willReturn(NULL);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($userStorage);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Authorization', 'Bearer test-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    $this->assertSame(500, $response->getStatusCode());
    $this->assertStringContainsString('uid 999', $response->getContent());
    $this->assertStringContainsString('not found', $response->getContent());
  }

  public function testHandleReturns500WhenServerProfileNotFound(): void {
    $remoteConfig = $this->createRemoteConfig([
      'enabled' => TRUE,
      'uid' => 5,
      'server_id' => 'missing_profile',
    ]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->method('validate')->willReturn(['key_id' => 'test', 'scopes' => ['read']]);
    $this->serverConfigRepository->method('getServer')->with('missing_profile')->willReturn(NULL);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Authorization', 'Bearer test-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    $this->assertSame(500, $response->getStatusCode());
    $this->assertStringContainsString('missing_profile', $response->getContent());
  }

  public function testHandleReturns403WhenServerAccessDenied(): void {
    $remoteConfig = $this->createRemoteConfig([
      'enabled' => TRUE,
      'uid' => 5,
      'server_id' => 'restricted_server',
    ]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->method('validate')->willReturn(['key_id' => 'test', 'scopes' => ['read']]);
    $this->serverConfigRepository->method('getServer')
      ->with('restricted_server')
      ->willReturn(['id' => 'restricted_server', 'name' => 'Restricted']);
    $this->serverConfigRepository->method('checkAccess')
      ->willReturn(['allowed' => FALSE, 'message' => 'IP not allowed']);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Authorization', 'Bearer test-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringContainsString('IP not allowed', $response->getContent());
  }

  public function testHandleReturns403WhenHttpTransportNotAllowed(): void {
    $remoteConfig = $this->createRemoteConfig([
      'enabled' => TRUE,
      'uid' => 5,
      'server_id' => 'stdio_only',
    ]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->method('validate')->willReturn(['key_id' => 'test', 'scopes' => ['read']]);
    $this->serverConfigRepository->method('getServer')
      ->with('stdio_only')
      ->willReturn(['id' => 'stdio_only', 'transports' => ['stdio']]);
    $this->serverConfigRepository->method('checkAccess')
      ->willReturn(['allowed' => TRUE]);
    $this->serverConfigRepository->method('allowsTransport')
      ->with($this->anything(), 'http')
      ->willReturn(FALSE);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Authorization', 'Bearer test-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringContainsString('HTTP transport', $response->getContent());
  }

  public function testHandleReturns403WhenNoPermittedScopes(): void {
    $remoteConfig = $this->createRemoteConfig([
      'enabled' => TRUE,
      'uid' => 5,
      'server_id' => 'admin_only',
    ]);
    $mainConfig = $this->createMainConfig(['access.allowed_scopes' => ['read']]);
    $configFactory = $this->setupConfigFactory($remoteConfig, $mainConfig);

    $this->apiKeyManager->method('validate')->willReturn([
      'key_id' => 'test',
      'scopes' => ['read'],
    ]);
    $this->serverConfigRepository->method('getServer')
      ->with('admin_only')
      ->willReturn(['id' => 'admin_only', 'scopes' => ['admin']]);
    $this->serverConfigRepository->method('checkAccess')
      ->willReturn(['allowed' => TRUE]);
    $this->serverConfigRepository->method('allowsTransport')
      ->willReturn(TRUE);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Authorization', 'Bearer test-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringContainsString('no permitted scopes', $response->getContent());
  }

  public function testHandleReturns404WhenOriginMismatch(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => TRUE]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST', [], [], [], [
      'HTTP_HOST' => 'example.com',
    ]);
    $request->headers->set('Origin', 'https://evil.com');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    $this->assertSame(404, $response->getStatusCode());
  }

  public function testHandleAllowsMatchingOrigin(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => TRUE, 'uid' => 0]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->method('validate')->willReturn(['key_id' => 'test', 'scopes' => ['read']]);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST', [], [], [], [
      'HTTP_HOST' => 'example.com',
    ]);
    $request->headers->set('Origin', 'https://example.com');
    $request->headers->set('Authorization', 'Bearer test-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    // Should pass origin check and fail at execution user.
    $this->assertSame(500, $response->getStatusCode());
    $this->assertStringContainsString('Execution user', $response->getContent());
  }

  public function testHandleAllowsOriginInAllowlist(): void {
    $remoteConfig = $this->createRemoteConfig([
      'enabled' => TRUE,
      'uid' => 0,
      'allowed_origins' => ['trusted.com', '*.example.com'],
    ]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->method('validate')->willReturn(['key_id' => 'test', 'scopes' => ['read']]);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST', [], [], [], [
      'HTTP_HOST' => 'other.com',
    ]);
    $request->headers->set('Origin', 'https://trusted.com');
    $request->headers->set('Authorization', 'Bearer test-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $response = $controller->handle($request);

    // Should pass origin check.
    $this->assertSame(500, $response->getStatusCode());
    $this->assertStringContainsString('Execution user', $response->getContent());
  }

  public function testHandleSetsClientIdFromApiKey(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => TRUE, 'uid' => 5]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->method('validate')->willReturn([
      'key_id' => 'client_abc123',
      'scopes' => ['read'],
    ]);

    $user = $this->createMock(UserInterface::class);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(5)->willReturn($user);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($userStorage);

    // Account switcher should be called.
    $this->accountSwitcher->expects($this->once())->method('switchTo')->with($user);
    $this->accountSwitcher->expects($this->once())->method('switchBack');

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Authorization', 'Bearer test-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    // This will fail because Mcp\Server class doesn't exist in unit test context.
    $response = $controller->handle($request);

    // Verify client ID was set on request.
    $this->assertSame('remote_key:client_abc123', $request->attributes->get('mcp_tools.client_id'));
  }

  public function testHandleSetsScopes(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => TRUE, 'uid' => 5]);
    $mainConfig = $this->createMainConfig(['access.allowed_scopes' => ['read', 'write', 'admin']]);
    $configFactory = $this->setupConfigFactory($remoteConfig, $mainConfig);

    $this->apiKeyManager->method('validate')->willReturn([
      'key_id' => 'test',
      'scopes' => ['read', 'write'],
    ]);

    $user = $this->createMock(UserInterface::class);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(5)->willReturn($user);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($userStorage);

    // Access manager should receive intersected scopes.
    $this->accessManager->expects($this->once())
      ->method('setScopes')
      ->with(['read', 'write']);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Authorization', 'Bearer test-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    // Will fail at MCP SDK check but scopes were set.
    $controller->handle($request);
  }

  public function testHandleDefaultsToReadScopeWhenNoIntersection(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => TRUE, 'uid' => 5]);
    $mainConfig = $this->createMainConfig(['access.allowed_scopes' => ['admin']]);
    $configFactory = $this->setupConfigFactory($remoteConfig, $mainConfig);

    $this->apiKeyManager->method('validate')->willReturn([
      'key_id' => 'test',
      'scopes' => ['read', 'write'],
    ]);

    $user = $this->createMock(UserInterface::class);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(5)->willReturn($user);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($userStorage);

    // When no intersection, defaults to ['read'].
    $this->accessManager->expects($this->once())
      ->method('setScopes')
      ->with(['read']);

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Authorization', 'Bearer test-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    $controller->handle($request);
  }

  public function testHandleSwitchesBackEvenOnException(): void {
    $remoteConfig = $this->createRemoteConfig(['enabled' => TRUE, 'uid' => 5]);
    $configFactory = $this->setupConfigFactory($remoteConfig);

    $this->apiKeyManager->method('validate')->willReturn([
      'key_id' => 'test',
      'scopes' => ['read'],
    ]);

    $user = $this->createMock(UserInterface::class);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(5)->willReturn($user);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($userStorage);

    // switchBack should always be called.
    $this->accountSwitcher->expects($this->once())->method('switchTo');
    $this->accountSwitcher->expects($this->once())->method('switchBack');

    $controller = $this->createController($configFactory);
    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Authorization', 'Bearer test-key');
    $request->headers->set('Accept', 'application/json, text/event-stream');

    // This will fail at MCP SDK check but switchBack should still be called.
    $controller->handle($request);
  }

}
