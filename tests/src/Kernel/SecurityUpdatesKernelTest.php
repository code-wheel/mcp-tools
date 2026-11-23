<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * "No security issues" is not said when Drupal could not look.
 *
 * Projects Drupal could not check were left out, so a site whose update
 * data could not be fetched read as having no security issues.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class SecurityUpdatesKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'update', 'dblog', 'tool', 'mcp_tools'];

  /**
   * With nothing fetched, the answer says it is not complete.
   */
  public function testAnAnswerSaysWhenItCouldNotLook(): void {
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'update', 'mcp_tools']);
    // A place nothing answers: nothing can be fetched.
    $this->config('update.settings')->set('fetch.url', 'http://127.0.0.1:9/release-history')->save();
    $result = $this->container->get('mcp_tools.security_update_checker')->getAvailableUpdates(TRUE);
    if (isset($result['error'])) {
      // Nothing at all came back: the tool fails, which is honest too.
      $this->assertStringContainsString('Unable', $result['error']);
      return;
    }
    $this->assertFalse($result['has_security_issues']);
    $this->assertArrayHasKey('complete', $result, 'The answer did not say whether every project was checked.');
    $this->assertFalse($result['complete']);
    $this->assertContains('drupal', $result['not_checked']);
  }

}
