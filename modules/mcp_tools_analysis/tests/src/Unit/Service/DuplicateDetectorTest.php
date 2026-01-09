<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\mcp_tools_analysis\Service\DuplicateDetector;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for DuplicateDetector.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_analysis\Service\DuplicateDetector::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_analysis')]
final class DuplicateDetectorTest extends UnitTestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private DuplicateDetector $detector;

  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->detector = new DuplicateDetector($this->entityTypeManager);
  }

  public function testFindDuplicateContentTypeNotFound(): void {
    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')->willReturnMap([
      ['node', $nodeStorage],
      ['node_type', $nodeTypeStorage],
    ]);

    $result = $this->detector->findDuplicateContent('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testFindDuplicateContentNoDuplicates(): void {
    $nodeType = $this->createMock(NodeTypeInterface::class);
    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->with('article')->willReturn($nodeType);

    $node1 = $this->createMockNode(1, 'First unique title');
    $node2 = $this->createMockNode(2, 'Second completely different title');

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([1, 2]);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($query);
    $nodeStorage->method('loadMultiple')->willReturn([1 => $node1, 2 => $node2]);

    $this->entityTypeManager->method('getStorage')->willReturnMap([
      ['node', $nodeStorage],
      ['node_type', $nodeTypeStorage],
    ]);

    $result = $this->detector->findDuplicateContent('article', 'title', 0.9);

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['duplicates']);
  }

  public function testFindDuplicateContentFindsDuplicates(): void {
    $nodeType = $this->createMock(NodeTypeInterface::class);
    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->with('article')->willReturn($nodeType);

    // Create nodes with very similar titles.
    $node1 = $this->createMockNode(1, 'Welcome to our amazing website');
    $node2 = $this->createMockNode(2, 'Welcome to our amazing website!');

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([1, 2]);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($query);
    $nodeStorage->method('loadMultiple')->willReturn([1 => $node1, 2 => $node2]);

    $this->entityTypeManager->method('getStorage')->willReturnMap([
      ['node', $nodeStorage],
      ['node_type', $nodeTypeStorage],
    ]);

    $result = $this->detector->findDuplicateContent('article', 'title', 0.8);

    $this->assertTrue($result['success']);
    $this->assertNotEmpty($result['data']['duplicates']);
  }

  public function testFindDuplicateContentNoNodes(): void {
    $nodeType = $this->createMock(NodeTypeInterface::class);
    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->with('article')->willReturn($nodeType);

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($query);
    $nodeStorage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')->willReturnMap([
      ['node', $nodeStorage],
      ['node_type', $nodeTypeStorage],
    ]);

    $result = $this->detector->findDuplicateContent('article');

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['data']['total_compared']);
  }

  /**
   * Creates a mock node.
   */
  private function createMockNode(int $id, string $title): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn($id);
    $node->method('getTitle')->willReturn($title);
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('getCreatedTime')->willReturn(time());
    $node->method('hasField')->willReturn(FALSE);

    return $node;
  }

}
