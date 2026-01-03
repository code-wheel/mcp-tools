<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_remote\Unit\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_remote\Controller\McpToolsRemoteController;
use Drupal\mcp_tools_remote\Service\ApiKeyManager;
use Drupal\Tests\UnitTestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\mcp_tools_remote\Controller\McpToolsRemoteController
 * @group mcp_tools_remote
 */
final class McpToolsRemoteControllerTest extends UnitTestCase {

  private function createApiKeyManager(array &$stateStorage): ApiKeyManager {
    $stateStorage = [];

    $state = $this->createMock(StateInterface::class);
    $state->method('get')
      ->willReturnCallback(static function (string $key, mixed $default = NULL) use (&$stateStorage): mixed {
        return $stateStorage[$key] ?? $default;
      });
    $state->method('set')
      ->willReturnCallback(static function (string $key, mixed $value) use (&$stateStorage): void {
        $stateStorage[$key] = $value;
      });

    $privateKey = $this->createMock(PrivateKey::class);
    $privateKey->method('get')->willReturn('test-pepper');

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);

    return new ApiKeyManager($state, $privateKey, $time);
  }

  /**
   * @covers ::handle
   */
  public function testHandleReturnsNotFoundWhenDisabled(): void {
    $remoteConfig = $this->createMock(ImmutableConfig::class);
    $remoteConfig->method('get')->with('enabled')->willReturn(FALSE);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('mcp_tools_remote.settings')->willReturn($remoteConfig);

    $stateStorage = [];
    $controller = new McpToolsRemoteController(
      $configFactory,
      $this->createApiKeyManager($stateStorage),
      $this->createMock(AccessManager::class),
      $this->createMock(PluginManagerInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(AccountSwitcherInterface::class),
      $this->createMock(EventDispatcherInterface::class),
      $this->createMock(LoggerInterface::class),
    );

    $response = $controller->handle(Request::create('/_mcp_tools', 'POST'));
    $this->assertSame(404, $response->getStatusCode());
  }

  /**
   * @covers ::handle
   */
  public function testHandleRequiresApiKey(): void {
    $remoteConfig = $this->createMock(ImmutableConfig::class);
    $remoteConfig->method('get')->willReturnMap([
      ['enabled', TRUE],
    ]);

    $mcpConfig = $this->createMock(ImmutableConfig::class);
    $mcpConfig->method('get')->willReturn(['read']);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturnMap([
      ['mcp_tools_remote.settings', $remoteConfig],
      ['mcp_tools.settings', $mcpConfig],
    ]);

    $stateStorage = [];
    $controller = new McpToolsRemoteController(
      $configFactory,
      $this->createApiKeyManager($stateStorage),
      $this->createMock(AccessManager::class),
      $this->createMock(PluginManagerInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(AccountSwitcherInterface::class),
      $this->createMock(EventDispatcherInterface::class),
      $this->createMock(LoggerInterface::class),
    );

    $response = $controller->handle(Request::create('/_mcp_tools', 'POST'));
    $this->assertSame(401, $response->getStatusCode());
    $this->assertSame('Bearer realm="mcp_tools_remote"', $response->headers->get('WWW-Authenticate'));
  }

  /**
   * @covers ::handle
   * @covers ::extractApiKey
   */
  public function testHandleReturnsErrorWhenExecutionUserMissing(): void {
    $remoteConfig = $this->createMock(ImmutableConfig::class);
    $remoteConfig->method('get')->willReturnMap([
      ['enabled', TRUE],
      ['uid', 999],
      ['server_name', 'Drupal MCP Tools'],
      ['server_version', '1.0.0'],
      ['pagination_limit', 50],
      ['include_all_tools', FALSE],
    ]);

    $mcpConfig = $this->createMock(ImmutableConfig::class);
    $mcpConfig->method('get')->with('access.allowed_scopes')->willReturn(['read', 'write']);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturnMap([
      ['mcp_tools_remote.settings', $remoteConfig],
      ['mcp_tools.settings', $mcpConfig],
    ]);

    $stateStorage = [];
    $apiKeyManager = $this->createApiKeyManager($stateStorage);
    $created = $apiKeyManager->createKey('Test', ['read']);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(999)->willReturn(NULL);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('user')->willReturn($userStorage);

    $controller = new McpToolsRemoteController(
      $configFactory,
      $apiKeyManager,
      $this->createMock(AccessManager::class),
      $this->createMock(PluginManagerInterface::class),
      $entityTypeManager,
      $this->createMock(AccountSwitcherInterface::class),
      $this->createMock(EventDispatcherInterface::class),
      $this->createMock(LoggerInterface::class),
    );

    $request = Request::create('/_mcp_tools', 'POST');
    $request->headers->set('Authorization', 'Bearer ' . $created['api_key']);

    $response = $controller->handle($request);
    $this->assertSame(500, $response->getStatusCode());
    $this->assertSame('Invalid execution user.', (string) $response->getContent());
  }

}
