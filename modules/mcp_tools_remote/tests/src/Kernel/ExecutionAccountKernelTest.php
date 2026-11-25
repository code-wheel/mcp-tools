<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_remote\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools_remote\Service\ExecutionAccount;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Only an account made for it is taken as the execution user.
 *
 * Setup took any account with the name it uses, and on a site with open
 * registration anyone can have registered that name.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_remote')]
final class ExecutionAccountKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_remote'];

  /**
   * A registered account of that name is not ours; one with the role is.
   */
  public function testARegisteredNameIsNotTaken(): void {
    $this->installEntitySchema('user');
    Role::create(['id' => 'executor', 'label' => 'Executor'])->save();
    $visitor = User::create(['name' => 'mcp_tools_remote', 'mail' => 'visitor@example.com', 'status' => 1]);
    $visitor->save();
    $this->assertNotNull(ExecutionAccount::refusal($visitor, 'executor'));

    // An address it uses, set by a visitor, makes no difference.
    $visitor->setEmail('mcp_tools_remote@example.invalid')->save();
    $this->assertNotNull(ExecutionAccount::refusal($visitor, 'executor'));

    $visitor->addRole('executor')->save();
    $this->assertNull(ExecutionAccount::refusal($visitor, 'executor'));
    $this->assertNull(ExecutionAccount::refusal(NULL, 'executor'));
  }

}
