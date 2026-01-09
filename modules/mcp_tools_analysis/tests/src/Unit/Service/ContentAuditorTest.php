<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\mcp_tools_analysis\Service\ContentAuditor;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for ContentAuditor.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_analysis\Service\ContentAuditor::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_analysis')]
final class ContentAuditorTest extends UnitTestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private Connection $database;
  private ContentAuditor $auditor;

  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->database = $this->createMock(Connection::class);
    $this->auditor = new ContentAuditor($this->entityTypeManager, $this->database);
  }

  public function testContentAuditReturnsStructuredResult(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);
    $this->entityTypeManager->method('hasDefinition')->willReturn(FALSE);

    $result = $this->auditor->contentAudit();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('stale_content', $result['data']);
    $this->assertArrayHasKey('orphaned_content', $result['data']);
    $this->assertArrayHasKey('drafts', $result['data']);
  }

  public function testContentAuditFindsOrphanedContent(): void {
    $unpublishedNode = $this->createMock(NodeInterface::class);
    $unpublishedNode->method('id')->willReturn(1);
    $unpublishedNode->method('getTitle')->willReturn('Draft Article');
    $unpublishedNode->method('bundle')->willReturn('article');
    $unpublishedNode->method('getCreatedTime')->willReturn(time() - 86400 * 30);
    $unpublishedNode->method('getChangedTime')->willReturn(time() - 86400 * 30);

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([1]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn([1 => $unpublishedNode]);

    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);
    $this->entityTypeManager->method('hasDefinition')->willReturn(FALSE);

    $result = $this->auditor->contentAudit();

    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['orphaned_count']);
  }

  public function testContentAuditByType(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);
    $this->entityTypeManager->method('hasDefinition')->willReturn(FALSE);

    $result = $this->auditor->contentAudit(['content_types' => ['article']]);

    $this->assertTrue($result['success']);
  }

}
