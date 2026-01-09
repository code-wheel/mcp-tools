<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\mcp_tools\Mcp\ServerConfigRepository;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\ServerConfigRepository::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ServerConfigRepositoryTest extends UnitTestCase {

  protected ConfigFactoryInterface $configFactory;
  protected ModuleHandlerInterface $moduleHandler;
  protected ContainerInterface $container;
  protected LoggerInterface $logger;
  protected ImmutableConfig $config;

  protected function setUp(): void {
    parent::setUp();
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->container = $this->createMock(ContainerInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->config = $this->createMock(ImmutableConfig::class);
    $this->configFactory->method('get')
      ->with('mcp_tools_servers.settings')
      ->willReturn($this->config);
  }

  protected function createRepository(): ServerConfigRepository {
    return new ServerConfigRepository(
      $this->configFactory,
      $this->moduleHandler,
      $this->container,
      $this->logger,
    );
  }

  public function testGetServersReturnsDefaultWhenEmpty(): void {
    $this->config->method('get')->willReturnMap([
      ['servers', NULL],
      ['default_server', NULL],
    ]);

    $repository = $this->createRepository();
    $servers = $repository->getServers();

    $this->assertArrayHasKey('default', $servers);
    $this->assertSame('default', $servers['default']['id']);
  }

  public function testGetServersAppliesDefaults(): void {
    $this->config->method('get')->willReturnMap([
      ['servers', ['test' => ['name' => 'Test Server']]],
      ['default_server', NULL],
    ]);

    $repository = $this->createRepository();
    $servers = $repository->getServers();

    $this->assertArrayHasKey('test', $servers);
    $server = $servers['test'];

    $this->assertSame('test', $server['id']);
    $this->assertSame('Test Server', $server['name']);
    $this->assertSame(50, $server['pagination_limit']);
    $this->assertFalse($server['include_all_tools']);
    $this->assertFalse($server['gateway_mode']);
    $this->assertTrue($server['enable_resources']);
    $this->assertTrue($server['enable_prompts']);
    $this->assertTrue($server['enabled']);
  }

  public function testGetServersFiltersDisabledServers(): void {
    $this->config->method('get')->willReturnMap([
      ['servers', [
        'enabled' => ['enabled' => TRUE],
        'disabled' => ['enabled' => FALSE],
      ]],
      ['default_server', NULL],
    ]);

    $repository = $this->createRepository();
    $servers = $repository->getServers();

    $this->assertArrayHasKey('enabled', $servers);
    $this->assertArrayNotHasKey('disabled', $servers);
  }

  public function testGetServersNormalizesScopes(): void {
    $this->config->method('get')->willReturnMap([
      ['servers', ['test' => ['scopes' => 'read,write,invalid']]],
      ['default_server', NULL],
    ]);

    $repository = $this->createRepository();
    $servers = $repository->getServers();

    $scopes = $servers['test']['scopes'];
    $this->assertContains('read', $scopes);
    $this->assertContains('write', $scopes);
    $this->assertNotContains('invalid', $scopes);
  }

  public function testGetServersNormalizesTransports(): void {
    $this->config->method('get')->willReturnMap([
      ['servers', ['test' => ['transports' => 'HTTP, stdio, invalid']]],
      ['default_server', NULL],
    ]);

    $repository = $this->createRepository();
    $servers = $repository->getServers();

    $transports = $servers['test']['transports'];
    $this->assertContains('http', $transports);
    $this->assertContains('stdio', $transports);
    $this->assertNotContains('invalid', $transports);
  }

  public function testGetServerReturnsNullForMissingServer(): void {
    $this->config->method('get')->willReturnMap([
      ['servers', ['default' => []]],
      ['default_server', NULL],
    ]);

    $repository = $this->createRepository();
    $server = $repository->getServer('nonexistent');

    $this->assertNull($server);
  }

  public function testGetServerReturnsDefaultWhenNullPassed(): void {
    $this->config->method('get')->willReturnMap([
      ['servers', ['default' => ['name' => 'Default']]],
      ['default_server', 'default'],
    ]);

    $repository = $this->createRepository();
    $server = $repository->getServer(NULL);

    $this->assertNotNull($server);
    $this->assertSame('Default', $server['name']);
  }

  public function testGetDefaultServerIdReturnsConfiguredDefault(): void {
    $this->config->method('get')->willReturnMap([
      ['servers', NULL],
      ['default_server', 'my_server'],
    ]);

    $repository = $this->createRepository();
    $servers = ['my_server' => [], 'other' => []];
    $defaultId = $repository->getDefaultServerId($servers);

    $this->assertSame('my_server', $defaultId);
  }

  public function testGetDefaultServerIdFallsBackToFirstServer(): void {
    $this->config->method('get')->willReturnMap([
      ['servers', NULL],
      ['default_server', 'nonexistent'],
    ]);

    $repository = $this->createRepository();
    $servers = ['first' => [], 'second' => []];
    $defaultId = $repository->getDefaultServerId($servers);

    $this->assertSame('first', $defaultId);
  }

  public function testCheckAccessAllowsWhenNoCallback(): void {
    $repository = $this->createRepository();
    $result = $repository->checkAccess(['permission_callback' => NULL], NULL);

    $this->assertTrue($result['allowed']);
    $this->assertNull($result['message']);
  }

  public function testCheckAccessWithBooleanCallback(): void {
    $repository = $this->createRepository();

    $allowConfig = ['permission_callback' => fn() => TRUE];
    $allowResult = $repository->checkAccess($allowConfig, NULL);
    $this->assertTrue($allowResult['allowed']);

    $denyConfig = ['permission_callback' => fn() => FALSE];
    $denyResult = $repository->checkAccess($denyConfig, NULL);
    $this->assertFalse($denyResult['allowed']);
    $this->assertNotNull($denyResult['message']);
  }

  public function testCheckAccessWithStringCallback(): void {
    $repository = $this->createRepository();

    $config = ['permission_callback' => fn() => 'Custom denial message'];
    $result = $repository->checkAccess($config, NULL);

    $this->assertFalse($result['allowed']);
    $this->assertSame('Custom denial message', $result['message']);
  }

  public function testCheckAccessWithAccessResultCallback(): void {
    $repository = $this->createRepository();

    $allowConfig = ['permission_callback' => fn() => AccessResult::allowed()];
    $allowResult = $repository->checkAccess($allowConfig, NULL);
    $this->assertTrue($allowResult['allowed']);

    $denyConfig = ['permission_callback' => fn() => AccessResult::forbidden()];
    $denyResult = $repository->checkAccess($denyConfig, NULL);
    $this->assertFalse($denyResult['allowed']);
  }

  public function testCheckAccessPassesRequestToCallback(): void {
    $request = Request::create('/test');
    $receivedRequest = NULL;

    $repository = $this->createRepository();
    $config = [
      'permission_callback' => function (?Request $req) use (&$receivedRequest) {
        $receivedRequest = $req;
        return TRUE;
      },
    ];

    $repository->checkAccess($config, $request);
    $this->assertSame($request, $receivedRequest);
  }

  public function testCheckAccessHandlesCallbackException(): void {
    $this->logger->expects($this->once())
      ->method('error')
      ->with($this->stringContains('permission callback failed'));

    $repository = $this->createRepository();
    $config = [
      'permission_callback' => fn() => throw new \RuntimeException('Test error'),
    ];

    $result = $repository->checkAccess($config, NULL);
    $this->assertFalse($result['allowed']);
    $this->assertStringContainsString('Access denied', $result['message']);
  }

  public function testCheckAccessResolvesServiceCallback(): void {
    $mockService = new class {

      public function checkPermission(): bool {
        return TRUE;
      }

    };

    $this->container->method('has')
      ->with('test_service')
      ->willReturn(TRUE);
    $this->container->method('get')
      ->with('test_service')
      ->willReturn($mockService);

    $repository = $this->createRepository();
    $config = ['permission_callback' => 'test_service:checkPermission'];

    $result = $repository->checkAccess($config, NULL);
    $this->assertTrue($result['allowed']);
  }

  public function testAllowsTransportWithEmptyConfig(): void {
    $repository = $this->createRepository();

    $result = $repository->allowsTransport(['transports' => []], 'http');
    $this->assertTrue($result);
  }

  public function testAllowsTransportMatchesConfigured(): void {
    $repository = $this->createRepository();
    $config = ['transports' => ['http', 'stdio']];

    $this->assertTrue($repository->allowsTransport($config, 'http'));
    $this->assertTrue($repository->allowsTransport($config, 'stdio'));
    $this->assertFalse($repository->allowsTransport($config, 'websocket'));
  }

  public function testAllowsTransportIsCaseInsensitive(): void {
    $repository = $this->createRepository();
    $config = ['transports' => ['http']];

    $this->assertTrue($repository->allowsTransport($config, 'HTTP'));
    $this->assertTrue($repository->allowsTransport($config, 'Http'));
  }

  public function testGetServersCallsAlterHook(): void {
    $this->config->method('get')->willReturnMap([
      ['servers', ['test' => []]],
      ['default_server', NULL],
    ]);

    $this->moduleHandler->expects($this->once())
      ->method('alter')
      ->with('mcp_tools_server_configs', $this->isType('array'));

    $repository = $this->createRepository();
    $repository->getServers();
  }

}
