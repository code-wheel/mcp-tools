<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_ultimate_cron\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_ultimate_cron\Service\UltimateCronService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for UltimateCronService.
 *
 * @coversDefaultClass \Drupal\mcp_tools_ultimate_cron\Service\UltimateCronService
 * @group mcp_tools_ultimate_cron
 */
class UltimateCronServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $jobStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
    $this->jobStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->with('ultimate_cron_job')
      ->willReturn($this->jobStorage);
  }

  /**
   * Creates a UltimateCronService instance.
   */
  protected function createService(): UltimateCronService {
    return new UltimateCronService(
      $this->entityTypeManager,
      $this->accessManager,
      $this->auditLogger
    );
  }

  /**
   * @covers ::listJobs
   */
  public function testListJobsEmpty(): void {
    $this->jobStorage->method('loadMultiple')->willReturn([]);

    $service = $this->createService();
    $result = $service->listJobs();

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['jobs']);
  }

  /**
   * @covers ::getJob
   */
  public function testGetJobNotFound(): void {
    $this->jobStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getJob('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::runJob
   */
  public function testRunJobAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->runJob('test_job');

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * @covers ::runJob
   */
  public function testRunJobNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->jobStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->runJob('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::enableJob
   */
  public function testEnableJobAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->enableJob('test_job');

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::enableJob
   */
  public function testEnableJobNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->jobStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->enableJob('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::disableJob
   */
  public function testDisableJobAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->disableJob('test_job');

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::disableJob
   */
  public function testDisableJobNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->jobStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->disableJob('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::getJobLogs
   */
  public function testGetJobLogsNotFound(): void {
    $this->jobStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getJobLogs('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::configureJob
   */
  public function testConfigureJobAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->configureJob('test_job', []);

    $this->assertFalse($result['success']);
  }

}
