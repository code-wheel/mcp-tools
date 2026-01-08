<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests for AccessManager service.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\AccessManager::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
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

  public function testCanReadWithoutReadScope(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.read_only_mode', FALSE],
        ['access.allowed_scopes', [AccessManager::SCOPE_WRITE]],
        ['access.default_scopes', [AccessManager::SCOPE_WRITE]],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $this->assertFalse($accessManager->canRead());
  }

  public function testCanReadFallsBackToReadWhenScopesEmpty(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.read_only_mode', FALSE],
        ['access.allowed_scopes', []],
        ['access.default_scopes', []],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $this->assertTrue($accessManager->canRead());
    $this->assertSame([AccessManager::SCOPE_READ], $accessManager->getCurrentScopes());
  }

  public function testIsConfigOnlyMode(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.config_only_mode', TRUE],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $this->assertTrue($accessManager->isConfigOnlyMode());
  }

  public function testIsWriteKindAllowedWhenConfigOnlyDisabled(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.config_only_mode', FALSE],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $this->assertTrue($accessManager->isWriteKindAllowed(AccessManager::WRITE_KIND_CONFIG));
    $this->assertTrue($accessManager->isWriteKindAllowed(AccessManager::WRITE_KIND_CONTENT));
    $this->assertTrue($accessManager->isWriteKindAllowed(AccessManager::WRITE_KIND_OPS));
    $this->assertTrue($accessManager->isWriteKindAllowed('unknown'));
  }

  public function testIsWriteKindAllowedDefaultsToConfigOnly(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.config_only_mode', TRUE],
        // Intentionally omit access.config_only_allowed_write_kinds to assert default.
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $this->assertTrue($accessManager->isWriteKindAllowed(AccessManager::WRITE_KIND_CONFIG));
    $this->assertFalse($accessManager->isWriteKindAllowed(AccessManager::WRITE_KIND_CONTENT));
    $this->assertFalse($accessManager->isWriteKindAllowed(AccessManager::WRITE_KIND_OPS));
    $this->assertFalse($accessManager->isWriteKindAllowed('unknown'));
  }

  public function testIsWriteKindAllowedWithConfiguredKinds(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.config_only_mode', TRUE],
        ['access.config_only_allowed_write_kinds', [AccessManager::WRITE_KIND_CONFIG, AccessManager::WRITE_KIND_OPS]],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $this->assertTrue($accessManager->isWriteKindAllowed(AccessManager::WRITE_KIND_CONFIG));
    $this->assertTrue($accessManager->isWriteKindAllowed(AccessManager::WRITE_KIND_OPS));
    $this->assertFalse($accessManager->isWriteKindAllowed(AccessManager::WRITE_KIND_CONTENT));
  }

  public function testIsWriteKindAllowedFallsBackWhenAllowedKindsEmpty(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.config_only_mode', TRUE],
        ['access.config_only_allowed_write_kinds', []],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $this->assertTrue($accessManager->isWriteKindAllowed(AccessManager::WRITE_KIND_CONFIG));
    $this->assertFalse($accessManager->isWriteKindAllowed(AccessManager::WRITE_KIND_CONTENT));
    $this->assertFalse($accessManager->isWriteKindAllowed(AccessManager::WRITE_KIND_OPS));
  }

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

  public function testGetCurrentScopesFromHeader(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.allowed_scopes', [AccessManager::SCOPE_READ, AccessManager::SCOPE_WRITE]],
        ['access.default_scopes', [AccessManager::SCOPE_READ]],
        ['access.trust_scopes_via_header', TRUE],
      ]);

    $request = new Request();
    $request->headers->set('X-MCP-Scope', 'read,write');

    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $accessManager = $this->createAccessManager();
    $scopes = $accessManager->getCurrentScopes();

    $this->assertContains(AccessManager::SCOPE_READ, $scopes);
    $this->assertContains(AccessManager::SCOPE_WRITE, $scopes);
    $this->assertNotContains(AccessManager::SCOPE_ADMIN, $scopes);
  }

  public function testGetCurrentScopesFromQueryParam(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.allowed_scopes', [AccessManager::SCOPE_READ, AccessManager::SCOPE_WRITE, AccessManager::SCOPE_ADMIN]],
        ['access.default_scopes', [AccessManager::SCOPE_READ]],
        ['access.trust_scopes_via_query', TRUE],
      ]);

    $request = Request::create('/', 'GET', ['mcp_scope' => 'admin']);

    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $accessManager = $this->createAccessManager();
    $scopes = $accessManager->getCurrentScopes();

    $this->assertContains(AccessManager::SCOPE_ADMIN, $scopes);
  }

  public function testGetCurrentScopesFiltersInvalidScopes(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['access.allowed_scopes', [AccessManager::SCOPE_READ, AccessManager::SCOPE_WRITE]],
        ['access.default_scopes', [AccessManager::SCOPE_READ]],
        ['access.trust_scopes_via_header', TRUE],
      ]);

    $request = new Request();
    $request->headers->set('X-MCP-Scope', 'read,invalid,write,superadmin');

    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $accessManager = $this->createAccessManager();
    $scopes = $accessManager->getCurrentScopes();

    $this->assertCount(2, $scopes);
    $this->assertContains(AccessManager::SCOPE_READ, $scopes);
    $this->assertContains(AccessManager::SCOPE_WRITE, $scopes);
    $this->assertNotContains('invalid', $scopes);
    $this->assertNotContains('superadmin', $scopes);
  }

  public function testSetScopes(): void {
    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $accessManager->setScopes([AccessManager::SCOPE_READ, AccessManager::SCOPE_ADMIN]);

    $this->assertTrue($accessManager->canRead());
    $this->assertFalse($accessManager->hasScope(AccessManager::SCOPE_WRITE));
    $this->assertTrue($accessManager->hasScope(AccessManager::SCOPE_ADMIN));
  }

  public function testSetScopesFiltersInvalid(): void {
    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $accessManager->setScopes(['read', 'invalid', 'superuser']);

    $scopes = $accessManager->getCurrentScopes();
    $this->assertCount(1, $scopes);
    $this->assertContains(AccessManager::SCOPE_READ, $scopes);
  }

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

  public function testIsReadOnlyModeTrue(): void {
    $this->config->method('get')
      ->with('access.read_only_mode')
      ->willReturn(TRUE);

    $accessManager = $this->createAccessManager();
    $this->assertTrue($accessManager->isReadOnlyMode());
  }

  public function testIsReadOnlyModeFalse(): void {
    $this->config->method('get')
      ->with('access.read_only_mode')
      ->willReturn(FALSE);

    $accessManager = $this->createAccessManager();
    $this->assertFalse($accessManager->isReadOnlyMode());
  }

  public function testHasScopeReturnsTrueForValidScope(): void {
    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $accessManager->setScopes([AccessManager::SCOPE_READ, AccessManager::SCOPE_WRITE]);

    $this->assertTrue($accessManager->hasScope(AccessManager::SCOPE_READ));
    $this->assertTrue($accessManager->hasScope(AccessManager::SCOPE_WRITE));
  }

  public function testHasScopeReturnsFalseForMissingScope(): void {
    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $accessManager = $this->createAccessManager();
    $accessManager->setScopes([AccessManager::SCOPE_READ]);

    $this->assertFalse($accessManager->hasScope(AccessManager::SCOPE_WRITE));
    $this->assertFalse($accessManager->hasScope(AccessManager::SCOPE_ADMIN));
  }

}
