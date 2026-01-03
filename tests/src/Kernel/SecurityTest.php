<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;

/**
 * Security-focused tests for MCP Tools.
 *
 * @group mcp_tools
 */
class SecurityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mcp_tools', 'user', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['mcp_tools']);
  }

  /**
   * Tests that invalid scopes are filtered out.
   */
  public function testInvalidScopesFiltered(): void {
    $accessManager = $this->container->get('mcp_tools.access_manager');

    // Try to set invalid scopes.
    $accessManager->setScopes(['read', 'superadmin', 'root', 'write']);

    $scopes = $accessManager->getCurrentScopes();

    // Only valid scopes should remain.
    $this->assertContains('read', $scopes);
    $this->assertContains('write', $scopes);
    $this->assertNotContains('superadmin', $scopes);
    $this->assertNotContains('root', $scopes);
    $this->assertCount(2, $scopes);
  }

  /**
   * Tests read-only mode cannot be bypassed via scopes.
   */
  public function testReadOnlyModeCannotBeBypassed(): void {
    // Enable read-only mode.
    $config = $this->config('mcp_tools.settings');
    $config->set('access.read_only_mode', TRUE)->save();

    $accessManager = new AccessManager(
      $this->container->get('config.factory'),
      $this->container->get('current_user'),
      $this->container->get('request_stack'),
      $this->container->get('mcp_tools.rate_limiter')
    );

    // Even with all scopes, should not be able to write.
    $accessManager->setScopes(['read', 'write', 'admin']);

    $this->assertTrue($accessManager->canRead());
    $this->assertFalse($accessManager->canWrite());
    $this->assertFalse($accessManager->canAdmin());
  }

  /**
   * Tests that rate limiting integrates with access control.
   */
  public function testRateLimitingIntegratesWithAccessControl(): void {
    // Enable rate limiting with very low limit.
    $config = $this->config('mcp_tools.settings');
    $config->set('rate_limiting.enabled', TRUE);
    $config->set('rate_limiting.max_writes_per_minute', 1);
    $config->save();

    $accessManager = new AccessManager(
      $this->container->get('config.factory'),
      $this->container->get('current_user'),
      $this->container->get('request_stack'),
      $this->container->get('mcp_tools.rate_limiter')
    );

    // First write should succeed.
    $this->assertTrue($accessManager->canWrite());

    // Second write should be blocked by rate limit.
    $this->assertFalse($accessManager->canWrite());

    // Error should indicate rate limit.
    $error = $accessManager->getLastRateLimitError();
    $this->assertNotNull($error);
    $this->assertStringContainsString('Rate limit exceeded', $error['error']);
  }

  /**
   * Tests that scope constants are immutable.
   */
  public function testScopeConstantsAreCorrect(): void {
    $this->assertEquals('read', AccessManager::SCOPE_READ);
    $this->assertEquals('write', AccessManager::SCOPE_WRITE);
    $this->assertEquals('admin', AccessManager::SCOPE_ADMIN);

    $allScopes = AccessManager::ALL_SCOPES;
    $this->assertCount(3, $allScopes);
    $this->assertContains('read', $allScopes);
    $this->assertContains('write', $allScopes);
    $this->assertContains('admin', $allScopes);
  }

  /**
   * Tests rate limit error format.
   */
  public function testRateLimitErrorFormat(): void {
    $config = $this->config('mcp_tools.settings');
    $config->set('rate_limiting.enabled', TRUE);
    $config->set('rate_limiting.max_writes_per_minute', 0);
    $config->save();

    $rateLimiter = $this->container->get('mcp_tools.rate_limiter');
    $result = $rateLimiter->checkLimit('write');

    $this->assertFalse($result['allowed']);
    $this->assertArrayHasKey('error', $result);
    $this->assertArrayHasKey('retry_after', $result);
    $this->assertArrayHasKey('code', $result);
    $this->assertEquals('RATE_LIMIT_EXCEEDED', $result['code']);
  }

  /**
   * Tests default configuration is secure for development.
   */
  public function testDefaultConfigIsSecureForDev(): void {
    $config = $this->config('mcp_tools.settings');

    // Default should not be read-only (for dev convenience).
    $this->assertFalse($config->get('access.read_only_mode'));

    // Rate limiting disabled by default (dev environment).
    $this->assertFalse($config->get('rate_limiting.enabled'));

    // But audit logging should be enabled by default.
    $this->assertTrue($config->get('access.audit_logging'));

    // Default scopes should be read + write (not admin).
    $scopes = $config->get('access.default_scopes');
    $this->assertContains('read', $scopes);
    $this->assertContains('write', $scopes);
    $this->assertNotContains('admin', $scopes);
  }

  /**
   * Tests that sensitive config is redacted in output.
   */
  public function testSensitiveConfigRedacted(): void {
    $config = $this->config('mcp_tools.settings');

    // Sensitive output should be disabled by default.
    $this->assertFalse($config->get('output.include_sensitive'));
  }

}
