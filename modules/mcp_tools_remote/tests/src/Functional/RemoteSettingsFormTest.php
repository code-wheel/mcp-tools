<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_remote\Functional;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * The remote endpoint's settings form, in a real browser session.
 *
 * The form had unit tests with mocked services only, so it was never
 * rendered or saved in a test.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_remote')]
final class RemoteSettingsFormTest extends BrowserTestBase {

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
   * The saved settings, read fresh.
   */
  private function settings(): ImmutableConfig {
    $factory = $this->container->get('config.factory');
    $factory->reset('mcp_tools_remote.settings');
    return $factory->get('mcp_tools_remote.settings');
  }

  /**
   * Only an administrator of MCP Tools reaches the form.
   */
  public function testFormNeedsThePermission(): void {
    $this->drupalLogin($this->drupalCreateUser(['access administration pages']));
    $this->drupalGet('/admin/config/services/mcp-tools/remote');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * The form refuses an endpoint without a user, and saves what was entered.
   */
  public function testFormSavesWhatWasEntered(): void {
    $this->drupalLogin($this->drupalCreateUser(['access administration pages', 'mcp_tools administer']));
    $this->drupalGet('/admin/config/services/mcp-tools/remote');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm(['enabled' => TRUE], 'Save configuration');
    $this->assertSession()->pageTextContains('An execution user is required when the endpoint is enabled.');
    $this->assertFalse($this->settings()->get('enabled'));

    // The button makes a dedicated account. Its role may use the tools of
    // some categories and may not administer MCP Tools.
    $this->submitForm([], 'Create MCP Executor Account');
    $account = user_load_by_name('mcp_executor');
    $this->assertNotFalse($account);
    $uid = (int) $account->id();
    $this->assertSame($uid, (int) $this->settings()->get('uid'));
    $role = Role::load('mcp_executor');
    $this->assertNotNull($role);
    $this->assertTrue($role->hasPermission('mcp_tools use site_health'));
    $this->assertFalse($role->hasPermission('mcp_tools use structure'));
    $this->assertFalse($role->hasPermission('mcp_tools administer'));

    // Saving the form keeps that user. The form read the user from a place
    // in the submitted values where it never is, so every save set the user
    // back to none and the endpoint could not be enabled here at all.
    $this->submitForm(['server_name' => 'Renamed'], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSame('Renamed', $this->settings()->get('server_name'));
    $this->assertSame($uid, (int) $this->settings()->get('uid'), 'Saving the form keeps the execution user.');

    $this->submitForm([
      'enabled' => TRUE,
      'allowed_ips' => "10.0.0.0/8\n\n 192.168.1.5 \n",
      'allowed_origins' => "example.com\n*.example.org",
      'server_name' => 'Test server',
      'pagination_limit' => 25,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $settings = $this->settings();
    $this->assertTrue($settings->get('enabled'));
    $this->assertSame($uid, (int) $settings->get('uid'));
    $this->assertFalse($settings->get('allow_uid1'));
    $this->assertSame(['10.0.0.0/8', '192.168.1.5'], $settings->get('allowed_ips'));
    $this->assertSame(['example.com', '*.example.org'], $settings->get('allowed_origins'));
    $this->assertSame('Test server', $settings->get('server_name'));
    $this->assertSame(25, $settings->get('pagination_limit'));

    // "uid 1 is allowed" is not "run as uid 1": with the override on and
    // another user set, the box is not ticked and a save keeps the user.
    $this->container->get('config.factory')->getEditable('mcp_tools_remote.settings')->set('allow_uid1', TRUE)->save();
    $this->drupalGet('/admin/config/services/mcp-tools/remote');
    $this->assertSession()->checkboxNotChecked('execution_user_wrapper[use_uid1]');
    $this->submitForm([], 'Save configuration');
    $this->assertSame($uid, (int) $this->settings()->get('uid'), 'The execution user is not switched to uid 1.');

    // The site admin runs the tools only when the box for it is ticked.
    $this->submitForm(['execution_user_wrapper[use_uid1]' => TRUE], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSame(1, (int) $this->settings()->get('uid'));
    $this->assertTrue($this->settings()->get('allow_uid1'));
  }

}
