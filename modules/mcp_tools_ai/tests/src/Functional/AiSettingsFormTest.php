<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_ai\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * The Drupal AI bridge's settings form, in a real browser session.
 *
 * It decides which kinds of tools an AI agent gets, and no test had ever
 * rendered or saved it. Needs drupal/ai, so it runs where the kernel tests
 * of this submodule run.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_ai')]
final class AiSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mcp_tools',
    'mcp_tools_ai',
    'ai',
    'key',
    'tool',
    'dblog',
    'update',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Read-only is the default, and the form saves what was chosen.
   */
  public function testFormSavesTheChosenOperations(): void {
    $this->drupalLogin($this->drupalCreateUser(['access administration pages']));
    $this->drupalGet('/admin/config/services/mcp-tools/ai');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['access administration pages', 'mcp_tools administer']));
    $this->drupalGet('/admin/config/services/mcp-tools/ai');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->checkboxChecked('exposed_operations[read]');
    $this->assertSession()->checkboxNotChecked('exposed_operations[write]');

    $this->submitForm([
      'exposed_operations[explain]' => FALSE,
      'exposed_operations[write]' => TRUE,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $factory = $this->container->get('config.factory');
    $factory->reset('mcp_tools_ai.settings');
    $saved = $factory->get('mcp_tools_ai.settings')->get('exposed_operations');
    sort($saved);
    $this->assertSame(['read', 'write'], $saved);
  }

}
