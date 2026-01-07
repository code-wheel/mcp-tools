<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the MCP Tools status controller.
 *
 * @group mcp_tools
 */
class StatusControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mcp_tools',
    'mcp_tools_remote',
    'tool',
    'dblog',
    'update',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with admin permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer site configuration',
      'mcp_tools administer',
    ]);
  }

  /**
   * Tests status page access control.
   */
  public function testStatusPageAccess(): void {
    // Anonymous user should not have access.
    $this->drupalGet('/admin/config/services/mcp-tools/status');
    $this->assertSession()->pageTextNotContains('MCP Tools Status');
    $text = $this->getSession()->getPage()->getText();
    $this->assertTrue(
      str_contains($text, 'Access denied') || str_contains($text, 'Log in'),
      'Anonymous user should see access denied or a login prompt.'
    );

    // Admin user should have access.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools/status');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('MCP Tools Status');
  }

  /**
   * Tests access status section displays correctly.
   */
  public function testAccessStatusSection(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools/status');

    $this->assertSession()->pageTextContains('Access Status');
    $this->assertSession()->pageTextContains('Read-only mode:');
    $this->assertSession()->pageTextContains('Config-only mode:');
    $this->assertSession()->pageTextContains('Current scopes:');
    $this->assertSession()->pageTextContains('Can read:');
    $this->assertSession()->pageTextContains('Can write:');
    $this->assertSession()->pageTextContains('Can admin:');
  }

  /**
   * Tests rate limiting status section displays correctly.
   */
  public function testRateLimitingStatusSection(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools/status');

    $this->assertSession()->pageTextContains('Rate Limiting Status');
    // Default is disabled.
    $this->assertSession()->pageTextContains('Rate limiting is DISABLED');
  }

  /**
   * Tests rate limiting enabled shows different status.
   */
  public function testRateLimitingEnabledStatus(): void {
    // Enable rate limiting via config.
    $this->config('mcp_tools.settings')
      ->set('rate_limiting.enabled', TRUE)
      ->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools/status');

    $this->assertSession()->pageTextContains('Rate limiting is ENABLED');
    $this->assertSession()->pageTextContains('Client identifier:');
  }

  /**
   * Tests enabled submodules section.
   */
  public function testEnabledSubmodulesSection(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools/status');

    $this->assertSession()->pageTextContains('Enabled Submodules');
    // Base module submodules should be listed (disabled by default).
    $this->assertSession()->pageTextContains('mcp_tools_content');
    $this->assertSession()->pageTextContains('mcp_tools_structure');
    $this->assertSession()->pageTextContains('mcp_tools_users');
    $this->assertSession()->pageTextContains('mcp_tools_menus');
  }

  /**
   * Tests security recommendations with default config.
   */
  public function testSecurityRecommendationsDefault(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools/status');

    $this->assertSession()->pageTextContains('Security Recommendations');
    // Default config has read-only disabled and rate limiting disabled.
    $this->assertSession()->pageTextContains('Read-only mode is disabled');
    $this->assertSession()->pageTextContains('Rate limiting is disabled');
  }

  /**
   * Tests security recommendations when all settings are secure.
   */
  public function testSecurityRecommendationsSecure(): void {
    // Configure for maximum security.
    $this->config('mcp_tools.settings')
      ->set('access.read_only_mode', TRUE)
      ->set('rate_limiting.enabled', TRUE)
      ->set('access.audit_logging', TRUE)
      ->set('access.default_scopes', ['read'])
      ->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools/status');

    $this->assertSession()->pageTextContains('All security recommendations met');
  }

  /**
   * Tests warning when write scope is default.
   */
  public function testWriteScopeWarning(): void {
    // Set write as default scope.
    $this->config('mcp_tools.settings')
      ->set('access.read_only_mode', TRUE)
      ->set('rate_limiting.enabled', TRUE)
      ->set('access.audit_logging', TRUE)
      ->set('access.default_scopes', ['read', 'write'])
      ->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools/status');

    $this->assertSession()->pageTextContains('Write/admin scopes enabled by default');
  }

  /**
   * Tests warning when audit logging is disabled.
   */
  public function testAuditLoggingWarning(): void {
    $this->config('mcp_tools.settings')
      ->set('access.read_only_mode', TRUE)
      ->set('rate_limiting.enabled', TRUE)
      ->set('access.audit_logging', FALSE)
      ->set('access.default_scopes', ['read'])
      ->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools/status');

    $this->assertSession()->pageTextContains('Audit logging is disabled');
  }

  /**
   * Tests read-only mode displays ENABLED.
   */
  public function testReadOnlyModeEnabled(): void {
    $this->config('mcp_tools.settings')
      ->set('access.read_only_mode', TRUE)
      ->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools/status');

    $this->assertSession()->pageTextContains('Read-only mode: ENABLED');
  }

  /**
   * Tests navigation between settings and status pages.
   */
  public function testNavigationBetweenPages(): void {
    $this->drupalLogin($this->adminUser);

    // Start at settings page.
    $this->drupalGet('/admin/config/services/mcp-tools');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('MCP Tools Settings');

    // Navigate to status page.
    $this->drupalGet('/admin/config/services/mcp-tools/status');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('MCP Tools Status');
  }

  /**
   * Tests remote endpoint section and warnings.
   */
  public function testRemoteEndpointWarnings(): void {
    $this->config('mcp_tools_remote.settings')
      ->set('enabled', TRUE)
      ->set('uid', 1)
      ->set('allowed_ips', [])
      ->set('include_all_tools', TRUE)
      ->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools/status');

    $this->assertSession()->pageTextContains('Remote HTTP Endpoint (Experimental)');
    $this->assertSession()->pageTextContains('Remote HTTP endpoint is enabled');
    $this->assertSession()->pageTextContains('Remote endpoint is configured to run as uid 1');
    $this->assertSession()->pageTextContains('Remote endpoint IP allowlist is empty');
    $this->assertSession()->pageTextContains('Remote endpoint is configured to expose all Tool API tools');
  }

}
