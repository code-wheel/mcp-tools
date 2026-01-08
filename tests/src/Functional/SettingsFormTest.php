<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the MCP Tools settings form.
 *
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
class SettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mcp_tools',
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
   * Tests that the settings form is accessible.
   */
  public function testSettingsFormAccess(): void {
    // Anonymous user should not have access.
    $this->drupalGet('/admin/config/services/mcp-tools');
    $this->assertSession()->pageTextNotContains('MCP Tools Settings');
    $text = $this->getSession()->getPage()->getText();
    $this->assertTrue(
      str_contains($text, 'Access denied') || str_contains($text, 'Log in'),
      'Anonymous user should see access denied or a login prompt.'
    );

    // Admin user should have access.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('MCP Tools Settings');
  }

  /**
   * Tests the settings form submission.
   */
  public function testSettingsFormSubmission(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('MCP Tools Settings');

    // Check that form elements exist.
    $this->assertSession()->fieldExists('read_only_mode');
    $this->assertSession()->fieldExists('config_only_mode');
    $this->assertSession()->fieldExists('enabled');

    // Submit the form with read-only mode and config-only mode enabled.
    $this->submitForm([
      'read_only_mode' => TRUE,
      'config_only_mode' => TRUE,
      'config_only_allowed_write_kinds[config]' => TRUE,
      'config_only_allowed_write_kinds[ops]' => TRUE,
    ], 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify the setting was saved.
    $config = $this->config('mcp_tools.settings');
    $this->assertTrue($config->get('access.read_only_mode'));
    $this->assertTrue($config->get('access.config_only_mode'));
    $this->assertSame(['config', 'ops'], $config->get('access.config_only_allowed_write_kinds'));
  }

  /**
   * Tests the status page.
   */
  public function testStatusPage(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools/status');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('MCP Tools Status');
  }

}
