<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_pathauto\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_pathauto\Service\PathautoService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for PathautoService.
 *
 * @coversDefaultClass \Drupal\mcp_tools_pathauto\Service\PathautoService
 * @group mcp_tools_pathauto
 */
class PathautoServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
  }

  /**
   * Creates a PathautoService instance.
   */
  protected function createService(): PathautoService {
    return new PathautoService(
      $this->entityTypeManager,
      $this->accessManager,
      $this->auditLogger
    );
  }

  /**
   * @covers ::listPatterns
   */
  public function testListPatternsEmpty(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn([]);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $service = $this->createService();
    $result = $service->listPatterns();

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['patterns']);
  }

  /**
   * @covers ::getPattern
   */
  public function testGetPatternNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $service = $this->createService();
    $result = $service->getPattern('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::createPattern
   */
  public function testCreatePatternAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->createPattern('test', 'Test', 'node', '/test/[node:title]');

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * @covers ::deletePattern
   */
  public function testDeletePatternAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->deletePattern('test');

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::deletePattern
   */
  public function testDeletePatternNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $service = $this->createService();
    $result = $service->deletePattern('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::generateAliases
   */
  public function testGenerateAliasesAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->generateAliases('node');

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::deleteAliases
   */
  public function testDeleteAliasesAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->deleteAliases('node');

    $this->assertFalse($result['success']);
  }

}
