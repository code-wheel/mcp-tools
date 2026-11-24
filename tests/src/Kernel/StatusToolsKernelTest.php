<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * The cache and cron status tools say what they found.
 *
 * Each wrapped its service's answer, already {success, data}, in another
 * "data", so every declared output was empty and the tool said success.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class StatusToolsKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_cache', 'mcp_tools_cron'];

  /**
   * Both tools, and the outputs each must fill.
   */
  public function testTheStatusToolsFillTheirOutputs(): void {
    $this->installEntitySchema('user');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'mcp_tools']);
    $this->setUpCurrentUser([], [], TRUE);
    foreach (['mcp_cache_get_status' => ['total_bins', 'bins'], 'mcp_cron_get_status' => ['last_run', 'is_overdue']] as $tool => $outputs) {
      $instance = $this->container->get('plugin.manager.tool')->createInstance($tool);
      $instance->execute();
      $this->assertTrue($instance->getResult()->isSuccess(), (string) $instance->getResult()->getMessage());
      $values = $instance->getOutputValues();
      foreach ($outputs as $output) {
        $this->assertNotNull($values[$output] ?? NULL, "$tool said success with $output empty.");
      }
    }
  }

}
