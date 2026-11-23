<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * The analysis tools show only content the caller may see.
 *
 * They queried with accessCheck(FALSE), so a read session listed the
 * titles of unpublished content its user may not view.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_analysis')]
final class AnalysisAccessKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_analysis'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'filter', 'node', 'mcp_tools']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->createUser([], 'admin');
    Node::create(['type' => 'page', 'title' => 'Secret plan', 'status' => 0, 'uid' => 1])->save();
    Node::create(['type' => 'page', 'title' => 'Secret plan', 'status' => 0, 'uid' => 1])->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read']);
  }

  /**
   * A reader who may not see unpublished content does not get its titles.
   */
  public function testUnpublishedTitlesStayHidden(): void {
    $this->setUpCurrentUser([], ['access content']);
    $audit = json_encode($this->container->get('mcp_tools_analysis.content_auditor')->contentAudit(['include_drafts' => TRUE]));
    $this->assertStringNotContainsString('Secret plan', $audit, 'The content audit gave out an unpublished title.');
    $duplicates = json_encode($this->container->get('mcp_tools_analysis.duplicate_detector')->findDuplicateContent('page'));
    $this->assertStringNotContainsString('Secret plan', $duplicates, 'The duplicate finder gave out an unpublished title.');

    // Someone who may see it still does.
    $this->setUpCurrentUser([], ['access content', 'bypass node access']);
    $audit = json_encode($this->container->get('mcp_tools_analysis.content_auditor')->contentAudit());
    $this->assertStringContainsString('Secret plan', $audit);
  }

}
