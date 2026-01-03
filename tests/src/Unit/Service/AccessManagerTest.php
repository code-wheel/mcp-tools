<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests for AccessManager service.
 *
 * @coversDefaultClass \Drupal\mcp_tools\Service\AccessManager
 * @group mcp_tools
 */
class AccessManagerTest extends UnitTestCase {

  protected ConfigFactoryInterface $configFactory;
  protected AccountProxyInterface $currentUser;
  protected RequestStack $requestStack;
  protected ImmutableConfig $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config = $this->createMock(ImmutableConfig::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('get')
      ->with('mcp_tools.settings')
      ->willReturn($this->config);

    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->requestStack = $this->createMock(RequestStack::class);
  }

  /**
   * Creates an AccessManager instance with the mocked dependencies.
   */
  protected function createAccessManager(): AccessManager {
    return new AccessManager(
      $this->configFactory,
      $this->currentUser,
      $this->requestStack
    );
  }

  /**
   * @covers ::canRead
   */
  public function testCanReadWithReadScope(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.read_only_mode', FALSE],
        ['access.default_scopes', [AccessManager::SCOPE_READ]],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $this->assertTrue($accessManager->canRead());
  }

  /**
   * @covers ::canRead
   */
  public function testCanReadWithoutReadScope(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.read_only_mode', FALSE],
        ['access.default_scopes', []],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $this->assertFalse($accessManager->canRead());
  }

  /**
   * @covers ::canWrite
   */
  public function testCanWriteWithWriteScope(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.read_only_mode', FALSE],
        ['access.default_scopes', [AccessManager::SCOPE_READ, AccessManager::SCOPE_WRITE]],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $this->assertTrue($accessManager->canWrite());
  }

  /**
   * @covers ::canWrite
   */
  public function testCanWriteBlockedByReadOnlyMode(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.read_only_mode', TRUE],
        ['access.default_scopes', [AccessManager::SCOPE_READ, AccessManager::SCOPE_WRITE]],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $this->assertFalse($accessManager->canWrite());
  }

  /**
   * @covers ::canWrite
   */
  public function testCanWriteWithoutWriteScope(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.read_only_mode', FALSE],
        ['access.default_scopes', [AccessManager::SCOPE_READ]],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $this->assertFalse($accessManager->canWrite());
  }

  /**
   * @covers ::canAdmin
   */
  public function testCanAdminWithAdminScope(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.read_only_mode', FALSE],
        ['access.default_scopes', [AccessManager::SCOPE_ADMIN]],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $this->assertTrue($accessManager->canAdmin());
  }

  /**
   * @covers ::canAdmin
   */
  public function testCanAdminBlockedByReadOnlyMode(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.read_only_mode', TRUE],
        ['access.default_scopes', [AccessManager::SCOPE_ADMIN]],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $this->assertFalse($accessManager->canAdmin());
  }

  /**
   * @covers ::getCurrentScopes
   */
  public function testGetCurrentScopesFromHeader(): void {
    $request = $this->createMock(Request::class);
    $headers = $this->createMock(HeaderBag::class);
    $headers->method('has')->with('X-MCP-Scope')->willReturn(TRUE);
    $headers->method('get')->with('X-MCP-Scope')->willReturn('read,write');
    $request->headers = $headers;
    $request->query = new ParameterBag([]);

    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $accessManager = $this->createAccessManager();
    $scopes = $accessManager->getCurrentScopes();

    $this->assertContains(AccessManager::SCOPE_READ, $scopes);
    $this->assertContains(AccessManager::SCOPE_WRITE, $scopes);
    $this->assertNotContains(AccessManager::SCOPE_ADMIN, $scopes);
  }

  /**
   * @covers ::getCurrentScopes
   */
  public function testGetCurrentScopesFromQueryParam(): void {
    $request = $this->createMock(Request::class);
    $headers = $this->createMock(HeaderBag::class);
    $headers->method('has')->with('X-MCP-Scope')->willReturn(FALSE);
    $request->headers = $headers;
    $request->query = new ParameterBag(['mcp_scope' => 'admin']);

    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $accessManager = $this->createAccessManager();
    $scopes = $accessManager->getCurrentScopes();

    $this->assertContains(AccessManager::SCOPE_ADMIN, $scopes);
  }

  /**
   * @covers ::getCurrentScopes
   */
  public function testGetCurrentScopesFiltersInvalidScopes(): void {
    $request = $this->createMock(Request::class);
    $headers = $this->createMock(HeaderBag::class);
    $headers->method('has')->with('X-MCP-Scope')->willReturn(TRUE);
    $headers->method('get')->with('X-MCP-Scope')->willReturn('read,invalid,write,superadmin');
    $request->headers = $headers;
    $request->query = new ParameterBag([]);

    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $accessManager = $this->createAccessManager();
    $scopes = $accessManager->getCurrentScopes();

    $this->assertCount(2, $scopes);
    $this->assertContains(AccessManager::SCOPE_READ, $scopes);
    $this->assertContains(AccessManager::SCOPE_WRITE, $scopes);
    $this->assertNotContains('invalid', $scopes);
    $this->assertNotContains('superadmin', $scopes);
  }

  /**
   * @covers ::setScopes
   */
  public function testSetScopes(): void {
    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $accessManager->setScopes([AccessManager::SCOPE_READ, AccessManager::SCOPE_ADMIN]);

    $this->assertTrue($accessManager->canRead());
    $this->assertFalse($accessManager->hasScope(AccessManager::SCOPE_WRITE));
    $this->assertTrue($accessManager->hasScope(AccessManager::SCOPE_ADMIN));
  }

  /**
   * @covers ::setScopes
   */
  public function testSetScopesFiltersInvalid(): void {
    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $accessManager->setScopes(['read', 'invalid', 'superuser']);

    $scopes = $accessManager->getCurrentScopes();
    $this->assertCount(1, $scopes);
    $this->assertContains(AccessManager::SCOPE_READ, $scopes);
  }

  /**
   * @covers ::getWriteAccessDenied
   */
  public function testGetWriteAccessDeniedInReadOnlyMode(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.read_only_mode', TRUE],
        ['access.default_scopes', [AccessManager::SCOPE_READ]],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $response = $accessManager->getWriteAccessDenied();

    $this->assertFalse($response['success']);
    $this->assertStringContainsString('read-only mode', $response['error']);
    $this->assertEquals('READ_ONLY_MODE', $response['code']);
  }

  /**
   * @covers ::getWriteAccessDenied
   */
  public function testGetWriteAccessDeniedInsufficientScope(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.read_only_mode', FALSE],
        ['access.default_scopes', [AccessManager::SCOPE_READ]],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $response = $accessManager->getWriteAccessDenied();

    $this->assertFalse($response['success']);
    $this->assertStringContainsString('Scope:', $response['error']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $response['code']);
  }

  /**
   * @covers ::isReadOnlyMode
   */
  public function testIsReadOnlyModeTrue(): void {
    $this->config->method('get')
      ->with('access.read_only_mode')
      ->willReturn(TRUE);

    $accessManager = $this->createAccessManager();
    $this->assertTrue($accessManager->isReadOnlyMode());
  }

  /**
   * @covers ::isReadOnlyMode
   */
  public function testIsReadOnlyModeFalse(): void {
    $this->config->method('get')
      ->with('access.read_only_mode')
      ->willReturn(FALSE);

    $accessManager = $this->createAccessManager();
    $this->assertFalse($accessManager->isReadOnlyMode());
  }

  /**
   * @covers ::hasScope
   */
  public function testHasScopeReturnsTrueForValidScope(): void {
    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $accessManager->setScopes([AccessManager::SCOPE_READ, AccessManager::SCOPE_WRITE]);

    $this->assertTrue($accessManager->hasScope(AccessManager::SCOPE_READ));
    $this->assertTrue($accessManager->hasScope(AccessManager::SCOPE_WRITE));
  }

  /**
   * @covers ::hasScope
   */
  public function testHasScopeReturnsFalseForMissingScope(): void {
    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $accessManager->setScopes([AccessManager::SCOPE_READ]);

    $this->assertFalse($accessManager->hasScope(AccessManager::SCOPE_WRITE));
    $this->assertFalse($accessManager->hasScope(AccessManager::SCOPE_ADMIN));
  }

}
