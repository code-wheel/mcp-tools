<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_webform\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_webform\Service\WebformService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for WebformService.
 *
 * @coversDefaultClass \Drupal\mcp_tools_webform\Service\WebformService
 * @group mcp_tools_webform
 */
class WebformServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $webformStorage;
  protected EntityStorageInterface $submissionStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
    $this->webformStorage = $this->createMock(EntityStorageInterface::class);
    $this->submissionStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['webform', $this->webformStorage],
        ['webform_submission', $this->submissionStorage],
      ]);
  }

  /**
   * Creates a WebformService instance.
   */
  protected function createService(): WebformService {
    return new WebformService(
      $this->entityTypeManager,
      $this->accessManager,
      $this->auditLogger
    );
  }

  /**
   * @covers ::listWebforms
   */
  public function testListWebformsEmpty(): void {
    $this->webformStorage->method('loadMultiple')->willReturn([]);

    $service = $this->createService();
    $result = $service->listWebforms();

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['webforms']);
  }

  /**
   * @covers ::getWebform
   */
  public function testGetWebformNotFound(): void {
    $this->webformStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getWebform('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::listSubmissions
   */
  public function testListSubmissionsWebformNotFound(): void {
    $this->webformStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->listSubmissions('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::getSubmission
   */
  public function testGetSubmissionNotFound(): void {
    $this->submissionStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getSubmission(999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::deleteSubmission
   */
  public function testDeleteSubmissionAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->deleteSubmission(1);

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * @covers ::deleteSubmission
   */
  public function testDeleteSubmissionNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->submissionStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->deleteSubmission(999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::getSubmissionStats
   */
  public function testGetSubmissionStatsWebformNotFound(): void {
    $this->webformStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getSubmissionStats('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::purgeSubmissions
   */
  public function testPurgeSubmissionsAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->purgeSubmissions('test');

    $this->assertFalse($result['success']);
  }

}
