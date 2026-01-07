<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_scheduler\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_scheduler\Service\SchedulerService;

/**
 * Kernel tests for SchedulerService.
 *
 * @group mcp_tools_scheduler
 * @requires module scheduler
 */
final class SchedulerServiceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'scheduler',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_scheduler',
  ];

  /**
   * The scheduler service under test.
   */
  private SchedulerService $schedulerService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mcp_tools', 'scheduler']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    $this->schedulerService = $this->container->get('mcp_tools_scheduler.scheduler');

    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
  }

  /**
   * Test getting scheduled content.
   */
  public function testGetScheduledContent(): void {
    $result = $this->schedulerService->getScheduledContent();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('items', $result['data']);
  }

  /**
   * Test getting scheduled content by type.
   */
  public function testGetScheduledContentByType(): void {
    $result = $this->schedulerService->getScheduledContent('publish');
    $this->assertTrue($result['success']);

    $result = $this->schedulerService->getScheduledContent('unpublish');
    $this->assertTrue($result['success']);
  }

  /**
   * Test scheduling publish for non-existent entity.
   */
  public function testSchedulePublishNotFound(): void {
    $result = $this->schedulerService->schedulePublish('node', 99999, time() + 3600);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test scheduling unpublish for non-existent entity.
   */
  public function testScheduleUnpublishNotFound(): void {
    $result = $this->schedulerService->scheduleUnpublish('node', 99999, time() + 3600);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test canceling schedule for non-existent entity.
   */
  public function testCancelScheduleNotFound(): void {
    $result = $this->schedulerService->cancelSchedule('node', 99999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test getting schedule for non-existent entity.
   */
  public function testGetScheduleNotFound(): void {
    $result = $this->schedulerService->getSchedule('node', 99999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test write operations require write scope.
   */
  public function testWriteOperationsRequireWriteScope(): void {
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    $result = $this->schedulerService->schedulePublish('node', 1, time() + 3600);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);

    $result = $this->schedulerService->scheduleUnpublish('node', 1, time() + 3600);
    $this->assertFalse($result['success']);

    $result = $this->schedulerService->cancelSchedule('node', 1);
    $this->assertFalse($result['success']);
  }

}
