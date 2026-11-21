<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the MCP Tools settings form JavaScript interactions.
 *
 * Tests #states visibility and form behavior requiring JavaScript.
 *
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
class SettingsFormJsTest extends WebDriverTestBase {

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
      'administer site configuration',
      'mcp_tools administer',
    ]);
  }

  /**
   * Tests rate limiting fields visibility with #states.
   */
  public function testRateLimitingFieldsVisibility(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools');

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // Rate limiting checkbox should exist.
    $enabledCheckbox = $page->findField('enabled');
    $this->assertNotNull($enabledCheckbox, 'Rate limiting enabled checkbox exists');

    // Initially, rate limit fields should be hidden (checkbox unchecked by default).
    $maxWritesField = $page->findField('max_writes_per_minute');
    $this->assertFalse($maxWritesField->isVisible(), 'Max writes per minute hidden when rate limiting disabled');

    $maxWritesHourField = $page->findField('max_writes_per_hour');
    $this->assertFalse($maxWritesHourField->isVisible(), 'Max writes per hour hidden when rate limiting disabled');

    $maxDeletesField = $page->findField('max_deletes_per_hour');
    $this->assertFalse($maxDeletesField->isVisible(), 'Max deletes per hour hidden when rate limiting disabled');

    $maxStructureField = $page->findField('max_structure_changes_per_hour');
    $this->assertFalse($maxStructureField->isVisible(), 'Max structure changes hidden when rate limiting disabled');

    // Enable rate limiting.
    $enabledCheckbox->check();
    $this->assertSession()->waitForElementVisible('css', '[name="max_writes_per_minute"]');

    // Now rate limit fields should be visible.
    $this->assertTrue($maxWritesField->isVisible(), 'Max writes per minute visible when rate limiting enabled');
    $this->assertTrue($maxWritesHourField->isVisible(), 'Max writes per hour visible when rate limiting enabled');
    $this->assertTrue($maxDeletesField->isVisible(), 'Max deletes per hour visible when rate limiting enabled');
    $this->assertTrue($maxStructureField->isVisible(), 'Max structure changes visible when rate limiting enabled');

    // Disable rate limiting again.
    $enabledCheckbox->uncheck();
    $assert->waitForElementRemoved('css', '[name="max_writes_per_minute"]:visible');

    // Fields should be hidden again.
    $this->assertFalse($maxWritesField->isVisible(), 'Max writes per minute hidden after disabling');
  }

  /**
   * Tests form submission persists all settings correctly.
   */
  public function testFormSubmissionPersistence(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools');

    $page = $this->getSession()->getPage();

    // Enable rate limiting and set values.
    $page->findField('enabled')->check();
    $this->assertSession()->waitForElementVisible('css', '[name="max_writes_per_minute"]');

    $page->fillField('max_writes_per_minute', '25');
    $page->fillField('max_writes_per_hour', '400');
    $page->fillField('max_deletes_per_hour', '40');

    // Enable read-only mode.
    $page->findField('read_only_mode')->check();

    // Enable audit logging.
    $page->findField('audit_logging')->check();

    // Submit the form.
    $page->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify settings were saved.
    $config = $this->config('mcp_tools.settings');
    $this->assertTrue($config->get('access.read_only_mode'), 'Read-only mode saved');
    $this->assertTrue($config->get('access.audit_logging'), 'Audit logging saved');
    $this->assertTrue($config->get('rate_limiting.enabled'), 'Rate limiting enabled saved');
    $this->assertEquals(25, $config->get('rate_limiting.max_writes_per_minute'), 'Max writes per minute saved');
    $this->assertEquals(400, $config->get('rate_limiting.max_writes_per_hour'), 'Max writes per hour saved');
    $this->assertEquals(40, $config->get('rate_limiting.max_deletes_per_hour'), 'Max deletes per hour saved');
  }

  /**
   * Tests scope checkboxes save correctly.
   */
  public function testScopeCheckboxes(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools');

    $page = $this->getSession()->getPage();

    // Uncheck all scopes first, then select only 'read'.
    $page->uncheckField('default_scopes[read]');
    $page->uncheckField('default_scopes[write]');
    $page->uncheckField('default_scopes[admin]');

    $page->checkField('default_scopes[read]');

    // Submit.
    $page->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify.
    $config = $this->config('mcp_tools.settings');
    $scopes = $config->get('access.default_scopes');
    $this->assertEquals(['read'], $scopes, 'Only read scope saved');
  }

  /**
   * Tests SSRF allowed hosts saves correctly.
   */
  public function testSsrfAllowedHosts(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mcp-tools');

    $page = $this->getSession()->getPage();

    // Open SSRF details.
    $ssrfDetails = $page->find('css', 'details#edit-ssrf');
    if ($ssrfDetails && !$ssrfDetails->hasAttribute('open')) {
      $ssrfDetails->click();
      $this->assertSession()->waitForElementVisible('css', '[name="allowed_hosts"]');
    }

    // Set allowed hosts.
    $page->fillField('allowed_hosts', "example.com\n*.trusted.org\nlocalhost");

    // Submit.
    $page->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify.
    $config = $this->config('mcp_tools.settings');
    $hosts = $config->get('allowed_hosts');
    $this->assertContains('example.com', $hosts);
    $this->assertContains('*.trusted.org', $hosts);
    $this->assertContains('localhost', $hosts);
  }

}
