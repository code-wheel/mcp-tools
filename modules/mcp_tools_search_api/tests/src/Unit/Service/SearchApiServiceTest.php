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

    // Skip tests if search_api module interfaces aren't available.
    if (!interface_exists(IndexInterface::class)) {
      $this->markTestSkipped('Search API module is not installed.');
    }

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

  /**
   * A result the user may not view is left out, and says how many were.
   *
   * Results went back with label, URL and excerpt and no access check: on
   * an index without the content_access processor, unpublished titles.
   */
  public function testAResultTheUserMayNotSeeIsLeftOut(): void {
    $item = function (string $label, bool $mayView) {
      $entity = $this->createMock(\Drupal\Core\Entity\ContentEntityInterface::class);
      $entity->method('label')->willReturn($label);
      $entity->method('access')->willReturn($mayView);
      $entity->method('getEntityTypeId')->willReturn('node');
      $entity->method('hasLinkTemplate')->willReturn(FALSE);
      $object = $this->createMock(\Drupal\Core\TypedData\ComplexDataInterface::class);
      $object->method('getValue')->willReturn($entity);
      $result = $this->createMock(\Drupal\search_api\Item\ItemInterface::class);
      $result->method('getOriginalObject')->willReturn($object);
      $result->method('getId')->willReturn('entity:node/' . $label);
      $result->method('getExcerpt')->willReturn('Excerpt of ' . $label);
      return $result;
    };
    $resultSet = $this->createMock(ResultSetInterface::class);
    $resultSet->method('getResultItems')->willReturn([$item('Public', TRUE), $item('Secret draft', FALSE)]);
    $resultSet->method('getResultCount')->willReturn(2);
    $query = $this->createMock(QueryInterface::class);
    $query->method('keys')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($resultSet);
    $index = $this->createMock(IndexInterface::class);
    $index->method('status')->willReturn(TRUE);
    $index->method('query')->willReturn($query);
    $index->method('getField')->willReturn(NULL);
    $this->indexStorage->method('load')->willReturn($index);

    $result = $this->service->search('content', 'x');
    $this->assertStringNotContainsString('Secret draft', json_encode($result), 'A result the user may not view was given out.');
    $this->assertSame(['Public'], array_column($result['data']['items'], 'label'));
    $this->assertSame(1, $result['data']['not_shown']);

    // A filter on a field the index does not have is refused.
    $this->assertFalse($this->service->search('content', 'x', ['nope' => 1])['success'], 'A filter the index cannot use was dropped.');
  }

  /**
   * The index call is bounded, and says so when nothing was indexed.
   *
   * It said "Indexed 0 items" with success when the index was locked or
   * the backend failed, and ran a limit of 100000 in one request.
   */
  public function testIndexingIsBoundedAndHonest(): void {
    $server = $this->createMock(\Drupal\search_api\ServerInterface::class);
    $server->method('isAvailable')->willReturn(TRUE);
    $tracker = $this->createMock(TrackerInterface::class);
    $tracker->method('getTotalItemsCount')->willReturn(500);
    $tracker->method('getIndexedItemsCount')->willReturn(0);
    $index = $this->createMock(IndexInterface::class);
    $index->method('status')->willReturn(TRUE);
    $index->method('isReadOnly')->willReturn(FALSE);
    $index->method('hasValidServer')->willReturn(TRUE);
    $index->method('getServerInstance')->willReturn($server);
    $index->method('getTrackerInstance')->willReturn($tracker);
    $asked = NULL;
    $index->method('indexItems')->willReturnCallback(function (int $limit) use (&$asked) {
      $asked = $limit;
      return 0;
    });
    $this->indexStorage->method('load')->willReturn($index);

    $result = $this->service->indexItems('content', 100000);
    $this->assertSame(200, $asked, 'A limit of 100000 was run in one request.');
    $this->assertFalse($result['success'], 'Nothing was indexed and the call said success.');
    $this->assertSame('NOTHING_INDEXED', $result['code']);
  }

}
