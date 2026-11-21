<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_jsonapi\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * The JSON:API tools' settings form, in a real browser session.
 *
 * The form had a unit test with mocked services only, so it was never
 * rendered or saved in a test.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_jsonapi')]
final class JsonApiSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mcp_tools',
    'mcp_tools_jsonapi',
    'node',
    'tool',
    'dblog',
    'update',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The form shows the saved settings and saves what was entered.
   */
  public function testFormSavesWhatWasEntered(): void {
    // The form lists the entity types JSON:API has a resource for, and a
    // node has one per content type.
    $this->drupalCreateContentType(['type' => 'page']);

    $this->drupalLogin($this->drupalCreateUser(['access administration pages']));
    $this->drupalGet('/admin/config/services/mcp-tools/jsonapi');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['access administration pages', 'mcp_tools administer']));
    $this->drupalGet('/admin/config/services/mcp-tools/jsonapi');
    $this->assertSession()->statusCodeEquals(200);
    // Users are blocked as installed, and writes are allowed.
    $this->assertSession()->checkboxChecked('blocked_entity_types[user]');
    $this->assertSession()->checkboxChecked('allow_write_operations');

    $this->submitForm([
      'allowed_entity_types[node]' => TRUE,
      'allow_write_operations' => FALSE,
      'max_items_per_page' => 20,
      'include_relationships' => TRUE,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $factory = $this->container->get('config.factory');
    $factory->reset('mcp_tools_jsonapi.settings');
    $settings = $factory->get('mcp_tools_jsonapi.settings');
    $this->assertSame(['node'], $settings->get('allowed_entity_types'));
    $this->assertContains('user', $settings->get('blocked_entity_types'));
    $this->assertFalse($settings->get('allow_write_operations'));
    $this->assertSame(20, $settings->get('max_items_per_page'));
    $this->assertTrue($settings->get('include_relationships'));
  }

}
