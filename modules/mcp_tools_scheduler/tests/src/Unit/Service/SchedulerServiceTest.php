<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_scheduler\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_scheduler\Service\SchedulerService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for SchedulerService.
 *
 * @coversDefaultClass \Drupal\mcp_tools_scheduler\Service\SchedulerService
 * @group mcp_tools_scheduler
 */
class SchedulerServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $nodeStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
    $this->nodeStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($this->nodeStorage);
  }

  /**
   * Creates a SchedulerService instance.
   */
  protected function createService(): SchedulerService {
    return new SchedulerService(
      $this->entityTypeManager,
      $this->accessManager,
      $this->auditLogger
    );
  }

  /**
   * @covers ::listScheduledContent
   */
  public function testListScheduledContentEmpty(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orConditionGroup')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $this->nodeStorage->method('getQuery')->willReturn($query);
    $this->nodeStorage->method('loadMultiple')->willReturn([]);

    $service = $this->createService();
    $result = $service->listScheduledContent();

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['items']);
  }

  /**
   * @covers ::getSchedule
   */
  public function testGetScheduleNotFound(): void {
    $this->nodeStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getSchedule(999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::setSchedule
   */
  public function testSetScheduleAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->setSchedule(1, NULL, NULL);

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * @covers ::setSchedule
   */
  public function testSetScheduleNodeNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->nodeStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->setSchedule(999, NULL, NULL);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::cancelSchedule
   */
  public function testCancelScheduleAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->cancelSchedule(1);

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::cancelSchedule
   */
  public function testCancelScheduleNodeNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->nodeStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->cancelSchedule(999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::runScheduler
   */
  public function testRunSchedulerAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->runScheduler();

    $this->assertFalse($result['success']);
  }

}
