<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;

/**
 * Kernel tests for MCP Tools access control integration.
 *
 * @group mcp_tools
 */
class AccessControlKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mcp_tools',
    'mcp_server',
    'tool',
    'user',
    'system',
    'update',
    'dblog',
  ];

  /**
   * The access manager service.
   *
   * @var \Drupal\mcp_tools\Service\AccessManager
   */
  protected AccessManager $accessManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mcp_tools']);
    $this->accessManager = $this->container->get('mcp_tools.access_manager');
  }

  /**
   * Tests that default configuration allows read and write.
   */
  public function testDefaultConfigAllowsReadWrite(): void {
    $this->assertTrue($this->accessManager->canRead());
    $this->assertTrue($this->accessManager->canWrite());
    $this->assertFalse($this->accessManager->canAdmin());
  }

  /**
   * Tests read-only mode blocks writes.
   */
  public function testReadOnlyModeBlocksWrites(): void {
    // Enable read-only mode.
    $config = $this->config('mcp_tools.settings');
    $config->set('access.read_only_mode', TRUE)->save();

    // Clear cached scopes.
    $this->accessManager = $this->container->get('mcp_tools.access_manager');

    $this->assertTrue($this->accessManager->canRead());
    $this->assertFalse($this->accessManager->canWrite());
    $this->assertFalse($this->accessManager->canAdmin());
  }

  /**
   * Tests that scope restriction works.
   */
  public function testScopeRestriction(): void {
    // Set to read-only scope.
    $config = $this->config('mcp_tools.settings');
    $config->set('access.default_scopes', ['read'])->save();

    // Clear cached scopes by recreating the access manager.
    $this->accessManager = new AccessManager(
      $this->container->get('config.factory'),
      $this->container->get('current_user'),
      $this->container->get('request_stack'),
      $this->container->get('mcp_tools.rate_limiter')
    );

    $this->assertTrue($this->accessManager->canRead());
    $this->assertFalse($this->accessManager->canWrite());
  }

  /**
   * Tests that setScopes works correctly.
   */
  public function testSetScopesOverridesConfig(): void {
    // Default should allow read+write.
    $this->assertTrue($this->accessManager->canRead());
    $this->assertTrue($this->accessManager->canWrite());

    // Override with just read.
    $this->accessManager->setScopes([AccessManager::SCOPE_READ]);

    $this->assertTrue($this->accessManager->canRead());
    $this->assertFalse($this->accessManager->canWrite());
  }

  /**
   * Tests admin scope configuration.
   */
  public function testAdminScope(): void {
    $this->assertFalse($this->accessManager->canAdmin());

    $config = $this->config('mcp_tools.settings');
    $config->set('access.default_scopes', ['read', 'write', 'admin'])->save();

    $this->accessManager = new AccessManager(
      $this->container->get('config.factory'),
      $this->container->get('current_user'),
      $this->container->get('request_stack'),
      $this->container->get('mcp_tools.rate_limiter')
    );

    $this->assertTrue($this->accessManager->canAdmin());
  }

  /**
   * Tests write access denied response format.
   */
  public function testWriteAccessDeniedResponse(): void {
    // Set to read-only.
    $config = $this->config('mcp_tools.settings');
    $config->set('access.default_scopes', ['read'])->save();

    $this->accessManager = new AccessManager(
      $this->container->get('config.factory'),
      $this->container->get('current_user'),
      $this->container->get('request_stack'),
      $this->container->get('mcp_tools.rate_limiter')
    );

    $response = $this->accessManager->getWriteAccessDenied();

    $this->assertFalse($response['success']);
    $this->assertArrayHasKey('error', $response);
    $this->assertArrayHasKey('code', $response);
    $this->assertEquals('INSUFFICIENT_SCOPE', $response['code']);
  }

  /**
   * Tests read-only mode response format.
   */
  public function testReadOnlyModeResponse(): void {
    $config = $this->config('mcp_tools.settings');
    $config->set('access.read_only_mode', TRUE)->save();

    $this->accessManager = new AccessManager(
      $this->container->get('config.factory'),
      $this->container->get('current_user'),
      $this->container->get('request_stack'),
      $this->container->get('mcp_tools.rate_limiter')
    );

    $response = $this->accessManager->getWriteAccessDenied();

    $this->assertFalse($response['success']);
    $this->assertEquals('READ_ONLY_MODE', $response['code']);
    $this->assertStringContainsString('read-only mode', $response['error']);
  }

}
