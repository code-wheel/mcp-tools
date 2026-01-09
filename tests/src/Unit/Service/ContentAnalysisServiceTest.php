<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;
use Drupal\mcp_tools\Service\ContentAnalysisService;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\ContentAnalysisService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ContentAnalysisServiceTest extends UnitTestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private EntityFieldManagerInterface $entityFieldManager;
  private Connection $database;
  private ContentAnalysisService $service;

  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->database = $this->createMock(Connection::class);

    $this->service = new ContentAnalysisService(
      $this->entityTypeManager,
      $this->entityFieldManager,
      $this->database,
    );
  }

  private function createService(): ContentAnalysisService {
    return new ContentAnalysisService(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityFieldManagerInterface::class),
      $this->createMock(Connection::class),
    );
  }

  public function testSearchContentRequiresMinimumLength(): void {
    $service = $this->createService();
    $result = $service->searchContent('ab');

    $this->assertArrayHasKey('error', $result);
    $this->assertSame([], $result['results']);
  }

  public function testSimplifyFieldValueMapsCommonTypes(): void {
    $service = $this->createService();
    $method = new \ReflectionMethod($service, 'simplifyFieldValue');

    $this->assertNull($method->invoke($service, [], 'string'));
    $this->assertSame('x', $method->invoke($service, [['value' => 'x']], 'string'));
    $this->assertSame(TRUE, $method->invoke($service, [['value' => 1]], 'boolean'));
    $this->assertSame([1, 2], $method->invoke($service, [['target_id' => 1], ['target_id' => 2]], 'entity_reference'));
    $this->assertSame([['uri' => '/a', 'title' => 'A']], $method->invoke($service, [['uri' => '/a', 'title' => 'A']], 'link'));
  }

  public function testGetContentTypesReturnsEmptyWhenNone(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')
      ->with('node_type')
      ->willReturn($storage);

    $result = $this->service->getContentTypes();

    $this->assertSame(0, $result['total_types']);
    $this->assertEmpty($result['types']);
  }

  public function testGetContentTypesReturnsTypesWithFields(): void {
    $nodeType = $this->createMock(NodeTypeInterface::class);
    $nodeType->method('id')->willReturn('article');
    $nodeType->method('label')->willReturn('Article');
    $nodeType->method('getDescription')->willReturn('Use for blog posts.');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn(['article' => $nodeType]);

    $this->entityTypeManager->method('getStorage')
      ->with('node_type')
      ->willReturn($storage);

    $fieldStorageDef = $this->createMock(FieldStorageDefinitionInterface::class);
    $fieldStorageDef->method('isBaseField')->willReturn(FALSE);

    $fieldDef = $this->createMock(FieldDefinitionInterface::class);
    $fieldDef->method('getFieldStorageDefinition')->willReturn($fieldStorageDef);
    $fieldDef->method('getLabel')->willReturn('Body');
    $fieldDef->method('getType')->willReturn('text_with_summary');
    $fieldDef->method('isRequired')->willReturn(FALSE);

    $this->entityFieldManager->method('getFieldDefinitions')
      ->with('node', 'article')
      ->willReturn(['body' => $fieldDef]);

    $result = $this->service->getContentTypes();

    $this->assertSame(1, $result['total_types']);
    $this->assertSame('article', $result['types'][0]['id']);
    $this->assertSame('Article', $result['types'][0]['label']);
    $this->assertSame(1, $result['types'][0]['field_count']);
  }

  public function testGetRecentContentReturnsEmptyWhenNone(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $result = $this->service->getRecentContent();

    $this->assertSame(0, $result['total']);
    $this->assertEmpty($result['content']);
  }

  public function testGetRecentContentReturnsNodes(): void {
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('/node/1');

    $owner = $this->createMock(UserInterface::class);
    $owner->method('getDisplayName')->willReturn('admin');

    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(1);
    $node->method('uuid')->willReturn('uuid-123');
    $node->method('getTitle')->willReturn('Test Article');
    $node->method('bundle')->willReturn('article');
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('getCreatedTime')->willReturn(1704067200);
    $node->method('getChangedTime')->willReturn(1704153600);
    $node->method('getOwner')->willReturn($owner);
    $node->method('toUrl')->willReturn($url);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([1]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn([1 => $node]);

    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $result = $this->service->getRecentContent(10, 'article', 'created');

    $this->assertSame(1, $result['total']);
    $this->assertSame('created', $result['sorted_by']);
    $this->assertSame('Test Article', $result['content'][0]['title']);
    $this->assertSame('published', $result['content'][0]['status']);
    $this->assertSame('admin', $result['content'][0]['author']);
  }

  public function testGetContentByIdReturnsErrorWhenNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(999)->willReturn(NULL);

    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $result = $this->service->getContentById(999);

    $this->assertArrayHasKey('error', $result);
    $this->assertStringContainsString('999', $result['error']);
  }

  public function testGetContentByIdReturnsNodeData(): void {
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('/node/1');

    $owner = $this->createMock(UserInterface::class);
    $owner->method('getDisplayName')->willReturn('editor');

    $fieldItem = new class {
      public function getValue(): array {
        return [['value' => 'Field content']];
      }
    };

    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(1);
    $node->method('uuid')->willReturn('uuid-456');
    $node->method('getTitle')->willReturn('My Article');
    $node->method('bundle')->willReturn('article');
    $node->method('isPublished')->willReturn(FALSE);
    $node->method('getCreatedTime')->willReturn(1704067200);
    $node->method('getChangedTime')->willReturn(1704153600);
    $node->method('getOwner')->willReturn($owner);
    $node->method('toUrl')->willReturn($url);
    $node->method('get')->willReturn($fieldItem);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(1)->willReturn($node);

    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $fieldStorageDef = $this->createMock(FieldStorageDefinitionInterface::class);
    $fieldStorageDef->method('isBaseField')->willReturn(FALSE);

    $fieldDef = $this->createMock(FieldDefinitionInterface::class);
    $fieldDef->method('getFieldStorageDefinition')->willReturn($fieldStorageDef);
    $fieldDef->method('getLabel')->willReturn('Summary');
    $fieldDef->method('getType')->willReturn('string');

    $this->entityFieldManager->method('getFieldDefinitions')
      ->with('node', 'article')
      ->willReturn(['field_summary' => $fieldDef]);

    $result = $this->service->getContentById(1);

    $this->assertSame(1, $result['id']);
    $this->assertSame('My Article', $result['title']);
    $this->assertSame('unpublished', $result['status']);
    $this->assertSame('editor', $result['author']);
    $this->assertArrayHasKey('fields', $result);
    $this->assertArrayHasKey('field_summary', $result['fields']);
  }

  public function testSearchContentWithValidQuery(): void {
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('/node/1');

    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(1);
    $node->method('uuid')->willReturn('uuid-789');
    $node->method('getTitle')->willReturn('Matching Title');
    $node->method('bundle')->willReturn('page');
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('getChangedTime')->willReturn(1704153600);
    $node->method('toUrl')->willReturn($url);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([1]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn([1 => $node]);

    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $result = $this->service->searchContent('Matching');

    $this->assertSame('Matching', $result['query']);
    $this->assertSame(1, $result['total']);
    $this->assertSame('Matching Title', $result['results'][0]['title']);
  }

  public function testSearchContentReturnsEmptyResults(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $result = $this->service->searchContent('nonexistent');

    $this->assertSame(0, $result['total']);
    $this->assertEmpty($result['results']);
  }

}
