<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_migration\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * A site that updates from beta24 has the module on and Migrate off.
 *
 * Composer does not bring migrate_plus (only this submodule needs it), and
 * an update does not turn modules on. The services pointed at Migrate's
 * plugin manager, so such a site could not build its container.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_migration')]
final class WithoutMigratePlusKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_migration'];

  /**
   * The site builds, and the import says what it needs.
   */
  public function testTheImportSaysWhatItNeeds(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'node', 'mcp_tools']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);

    $result = $this->container->get('mcp_tools_migration.migration')->importFromJson('article', [['id' => '1', 'title' => 'One']], 'id');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('composer require drupal/migrate_plus', $result['error']);
    // The tools that do not need Migrate still work.
    $this->assertTrue($this->container->get('mcp_tools_migration.migration')->exportToJson('article')['success']);
  }

}
