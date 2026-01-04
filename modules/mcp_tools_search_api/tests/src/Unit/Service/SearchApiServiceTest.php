<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_search_api\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_search_api\Service\SearchApiService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for SearchApiService.
 *
 * @coversDefaultClass \Drupal\mcp_tools_search_api\Service\SearchApiService
 * @group mcp_tools_search_api
 */
class SearchApiServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $indexStorage;
  protected EntityStorageInterface $serverStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
    $this->indexStorage = $this->createMock(EntityStorageInterface::class);
    $this->serverStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['search_api_index', $this->indexStorage],
        ['search_api_server', $this->serverStorage],
      ]);
  }

  /**
   * Creates a SearchApiService instance.
   */
  protected function createService(): SearchApiService {
    return new SearchApiService(
      $this->entityTypeManager,
      $this->accessManager,
      $this->auditLogger
    );
  }

  /**
   * @covers ::listIndexes
   */
  public function testListIndexesEmpty(): void {
    $this->indexStorage->method('loadMultiple')->willReturn([]);

    $service = $this->createService();
    $result = $service->listIndexes();

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['indexes']);
  }

  /**
   * @covers ::getIndex
   */
  public function testGetIndexNotFound(): void {
    $this->indexStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getIndex('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::indexItems
   */
  public function testIndexItemsAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->indexItems('test_index');

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * @covers ::indexItems
   */
  public function testIndexItemsIndexNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->indexStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->indexItems('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::clearIndex
   */
  public function testClearIndexAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->clearIndex('test_index');

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::clearIndex
   */
  public function testClearIndexNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->indexStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->clearIndex('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::reindexIndex
   */
  public function testReindexIndexAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->reindexIndex('test_index');

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::listServers
   */
  public function testListServersEmpty(): void {
    $this->serverStorage->method('loadMultiple')->willReturn([]);

    $service = $this->createService();
    $result = $service->listServers();

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['servers']);
  }

  /**
   * @covers ::getServer
   */
  public function testGetServerNotFound(): void {
    $this->serverStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getServer('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

}
