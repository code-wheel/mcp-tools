<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Unit\Service;

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
  private ContentAuditor $auditor;

  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->auditor = new ContentAuditor($this->entityTypeManager);
  }

  public function testAuditContentReturnsStructuredResult(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $result = $this->auditor->auditContent();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('total_nodes', $result['data']);
    $this->assertArrayHasKey('issues', $result['data']);
    $this->assertArrayHasKey('summary', $result['data']);
  }

  public function testAuditContentFindsUnpublishedContent(): void {
    $unpublishedNode = $this->createMock(NodeInterface::class);
    $unpublishedNode->method('id')->willReturn(1);
    $unpublishedNode->method('getTitle')->willReturn('Draft Article');
    $unpublishedNode->method('isPublished')->willReturn(FALSE);
    $unpublishedNode->method('getType')->willReturn('article');
    $unpublishedNode->method('getCreatedTime')->willReturn(time() - 86400 * 30);
    $unpublishedNode->method('getChangedTime')->willReturn(time() - 86400 * 30);
    $unpublishedNode->method('getFields')->willReturn([]);

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([1]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn([1 => $unpublishedNode]);

    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $result = $this->auditor->auditContent();

    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['summary']['unpublished']);
  }

  public function testAuditContentByType(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $result = $this->auditor->auditContent('article');

    $this->assertTrue($result['success']);
  }

}
