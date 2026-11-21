<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;

/**
 * Kernel tests for MCP Tools access control integration.
 *
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
class AccessControlKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mcp_tools',
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
   * Tests that default configuration allows read-only access.
   */
  public function testDefaultConfigAllowsReadOnly(): void {
    $this->assertTrue($this->accessManager->canRead());
    $this->assertFalse($this->accessManager->canWrite());
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
    // Default should allow read-only.
    $this->assertTrue($this->accessManager->canRead());
    $this->assertFalse($this->accessManager->canWrite());

    // Override with read+write.
    $this->accessManager->setScopes([AccessManager::SCOPE_READ, AccessManager::SCOPE_WRITE]);

    $this->assertTrue($this->accessManager->canRead());
    $this->assertTrue($this->accessManager->canWrite());

    // Override back to read-only.
    $this->accessManager->setScopes([AccessManager::SCOPE_READ]);
    $this->assertFalse($this->accessManager->canWrite());
  }

  /**
   * Tests admin scope configuration.
   */
  public function testAdminScope(): void {
    $this->assertFalse($this->accessManager->canAdmin());

    $config = $this->config('mcp_tools.settings');
    $config->set('access.allowed_scopes', ['read', 'write', 'admin']);
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

  /**
   * The STDIO server keeps its scopes when a tool rebuilds the container.
   *
   * `drush mcp-tools:serve --scope=read,write` is one PHP process for a whole
   * client session. mcp_cache_clear_all rebuilds the container inside it,
   * which replaces the access manager: the session then fell back to the
   * default scopes and every later write was refused until a restart.
   */
  public function testProcessScopesSurviveAContainerRebuild(): void {
    $this->config('mcp_tools.settings')
      ->set('access.default_scopes', ['read'])
      ->save();

    // What the STDIO command does when it starts.
    $manager = $this->container->get('mcp_tools.access_manager');
    $manager->setScopes(['read', 'write']);
    AccessManager::setProcessScopes(['read', 'write']);
    try {
      $this->assertTrue($manager->hasScope('write'));

      // What mcp_cache_clear_all does inside the running server.
      $this->container->get('kernel')->rebuildContainer();
      $rebuilt = \Drupal::service('mcp_tools.access_manager');
      $this->assertNotSame($manager, $rebuilt, 'The rebuild replaced the service.');
      $this->assertTrue($rebuilt->hasScope('write'), 'The write scope is still there after the rebuild.');
      $this->assertFalse($rebuilt->hasScope('admin'), 'Nothing more than what was set.');
    }
    finally {
      AccessManager::setProcessScopes(NULL);
    }

    // Without process scopes a new instance uses the configured default.
    $this->container->get('kernel')->rebuildContainer();
    $this->assertFalse(\Drupal::service('mcp_tools.access_manager')->hasScope('write'));
  }

  /**
   * The link to the MCP client survives a container rebuild.
   *
   * The server arms one bridge object per call. After a cache clear in a
   * long STDIO session, tools were handed a new, unarmed one: progress
   * updates stopped and sampling reported "not supported".
   */
  public function testClientBridgeSurvivesAContainerRebuild(): void {
    $bridge = $this->container->get('mcp_tools.client_bridge');

    $this->container->get('kernel')->rebuildContainer();

    $this->assertSame($bridge, \Drupal::service('mcp_tools.client_bridge'));
  }

}
