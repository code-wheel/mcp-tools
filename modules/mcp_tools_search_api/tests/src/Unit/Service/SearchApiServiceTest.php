<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_search_api\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools_search_api\Service\SearchApiService;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Tracker\TrackerInterface;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(SearchApiService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_search_api')]
final class SearchApiServiceTest extends UnitTestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private EntityStorageInterface $indexStorage;
  private SearchApiService $service;

  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->indexStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($entityType) {
        if ($entityType === 'search_api_index') {
          return $this->indexStorage;
        }
        return $this->createMock(EntityStorageInterface::class);
      });

    $this->service = new SearchApiService($this->entityTypeManager);
  }

  public function testSearchReturnsErrorWhenIndexNotFound(): void {
    $this->indexStorage->method('load')->with('missing_index')->willReturn(NULL);

    $result = $this->service->search('missing_index', 'test keywords');

    $this->assertFalse($result['success']);
    $this->assertSame('NOT_FOUND', $result['code']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testSearchReturnsErrorWhenIndexDisabled(): void {
    $index = $this->createMock(IndexInterface::class);
    $index->method('status')->willReturn(FALSE);

    $this->indexStorage->method('load')->with('disabled_index')->willReturn($index);

    $result = $this->service->search('disabled_index', 'test keywords');

    $this->assertFalse($result['success']);
    $this->assertSame('INDEX_DISABLED', $result['code']);
  }

  public function testSearchReturnsResults(): void {
    $resultSet = $this->createMock(ResultSetInterface::class);
    $resultSet->method('getResultItems')->willReturn([]);
    $resultSet->method('getResultCount')->willReturn(0);

    $query = $this->createMock(QueryInterface::class);
    $query->method('keys')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($resultSet);

    $index = $this->createMock(IndexInterface::class);
    $index->method('status')->willReturn(TRUE);
    $index->method('query')->willReturn($query);
    $index->method('getField')->willReturn(NULL);

    $this->indexStorage->method('load')->with('content')->willReturn($index);

    $result = $this->service->search('content', 'test keywords');

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('data', $result);
    $this->assertArrayHasKey('items', $result['data']);
    $this->assertSame(0, $result['data']['total']);
    $this->assertSame('content', $result['data']['index']);
    $this->assertSame('test keywords', $result['data']['keywords']);
  }

  public function testSearchRespectsLimitAndOffset(): void {
    $resultSet = $this->createMock(ResultSetInterface::class);
    $resultSet->method('getResultItems')->willReturn([]);
    $resultSet->method('getResultCount')->willReturn(100);

    $query = $this->createMock(QueryInterface::class);
    $query->method('keys')->willReturnSelf();
    $query->expects($this->once())
      ->method('range')
      ->with(10, 25)
      ->willReturnSelf();
    $query->method('execute')->willReturn($resultSet);

    $index = $this->createMock(IndexInterface::class);
    $index->method('status')->willReturn(TRUE);
    $index->method('query')->willReturn($query);
    $index->method('getField')->willReturn(NULL);

    $this->indexStorage->method('load')->with('content')->willReturn($index);

    $result = $this->service->search('content', 'test', [], 25, 10);

    $this->assertTrue($result['success']);
    $this->assertSame(25, $result['data']['limit']);
    $this->assertSame(10, $result['data']['offset']);
    $this->assertTrue($result['data']['has_more']);
  }

  public function testSearchEnforcesMaxLimit(): void {
    $resultSet = $this->createMock(ResultSetInterface::class);
    $resultSet->method('getResultItems')->willReturn([]);
    $resultSet->method('getResultCount')->willReturn(0);

    $query = $this->createMock(QueryInterface::class);
    $query->method('keys')->willReturnSelf();
    // Should cap at 100 even if 500 requested.
    $query->expects($this->once())
      ->method('range')
      ->with(0, 100)
      ->willReturnSelf();
    $query->method('execute')->willReturn($resultSet);

    $index = $this->createMock(IndexInterface::class);
    $index->method('status')->willReturn(TRUE);
    $index->method('query')->willReturn($query);
    $index->method('getField')->willReturn(NULL);

    $this->indexStorage->method('load')->with('content')->willReturn($index);

    $result = $this->service->search('content', 'test', [], 500, 0);

    $this->assertTrue($result['success']);
  }

  public function testListIndexesReturnsEmptyArray(): void {
    $this->indexStorage->method('loadMultiple')->willReturn([]);

    $result = $this->service->listIndexes();

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['total']);
    $this->assertSame([], $result['indexes']);
  }

  public function testGetIndexReturnsErrorWhenNotFound(): void {
    $this->indexStorage->method('load')->with('missing')->willReturn(NULL);

    $result = $this->service->getIndex('missing');

    $this->assertFalse($result['success']);
    $this->assertSame('NOT_FOUND', $result['code']);
  }

  public function testReindexReturnsErrorForDisabledIndex(): void {
    $index = $this->createMock(IndexInterface::class);
    $index->method('status')->willReturn(FALSE);

    $this->indexStorage->method('load')->with('disabled')->willReturn($index);

    $result = $this->service->reindexIndex('disabled');

    $this->assertFalse($result['success']);
    $this->assertSame('INDEX_DISABLED', $result['code']);
  }

  public function testClearIndexReturnsErrorForReadOnlyIndex(): void {
    $index = $this->createMock(IndexInterface::class);
    $index->method('status')->willReturn(TRUE);
    $index->method('isReadOnly')->willReturn(TRUE);

    $this->indexStorage->method('load')->with('readonly')->willReturn($index);

    $result = $this->service->clearIndex('readonly');

    $this->assertFalse($result['success']);
    $this->assertSame('INDEX_READ_ONLY', $result['code']);
  }

}
