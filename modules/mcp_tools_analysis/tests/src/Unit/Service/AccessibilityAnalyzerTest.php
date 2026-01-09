<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Unit\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\mcp_tools_analysis\Service\AccessibilityAnalyzer;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for AccessibilityAnalyzer.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_analysis\Service\AccessibilityAnalyzer::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_analysis')]
final class AccessibilityAnalyzerTest extends UnitTestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private AccessibilityAnalyzer $analyzer;

  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->analyzer = new AccessibilityAnalyzer($this->entityTypeManager);
  }

  public function testCheckAccessibilityEntityNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(999)->willReturn(NULL);
    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $result = $this->analyzer->checkAccessibility('node', 999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testCheckAccessibilityImageMissingAlt(): void {
    $entity = $this->createMockEntity('<p>Text</p><img src="test.jpg"><p>More text</p>');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($entity);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $result = $this->analyzer->checkAccessibility('node', 1);

    $this->assertTrue($result['success']);
    $this->assertNotEmpty($result['issues']);

    $altIssue = array_filter($result['issues'], fn($i) => $i['type'] === 'missing_alt');
    $this->assertNotEmpty($altIssue);
  }

  public function testCheckAccessibilityEmptyAltWithoutRole(): void {
    $entity = $this->createMockEntity('<img src="test.jpg" alt="">');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($entity);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $result = $this->analyzer->checkAccessibility('node', 1);

    $emptyAltIssue = array_filter($result['issues'], fn($i) => $i['type'] === 'empty_alt');
    $this->assertNotEmpty($emptyAltIssue);
  }

  public function testCheckAccessibilityDecorativeImageIsOk(): void {
    $entity = $this->createMockEntity('<img src="test.jpg" alt="" role="presentation">');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($entity);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $result = $this->analyzer->checkAccessibility('node', 1);

    $emptyAltIssue = array_filter($result['issues'], fn($i) => $i['type'] === 'empty_alt');
    $this->assertEmpty($emptyAltIssue);
  }

  public function testCheckAccessibilityValidImageWithAlt(): void {
    $entity = $this->createMockEntity('<img src="test.jpg" alt="Description of image">');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($entity);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $result = $this->analyzer->checkAccessibility('node', 1);

    $altIssues = array_filter($result['issues'], fn($i) => str_contains($i['type'], 'alt'));
    $this->assertEmpty($altIssues);
  }

  public function testCheckAccessibilityNoContent(): void {
    $entity = $this->createMockEntity('');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($entity);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $result = $this->analyzer->checkAccessibility('node', 1);

    $this->assertTrue($result['success']);
  }

  /**
   * Creates a mock entity with text content.
   */
  private function createMockEntity(string $bodyContent): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('label')->willReturn('Test Title');

    // Create a mock field with text content.
    $fieldDef = $this->createMock(FieldDefinitionInterface::class);
    $fieldDef->method('getType')->willReturn('text_with_summary');

    $fieldItem = new \stdClass();
    $fieldItem->value = $bodyContent;

    // Create an anonymous class that implements FieldItemListInterface and
    // IteratorAggregate to allow iteration over field items.
    $fieldList = new class($fieldDef, [$fieldItem]) implements \IteratorAggregate {
      private $fieldDef;
      private $items;

      public function __construct($fieldDef, $items) {
        $this->fieldDef = $fieldDef;
        $this->items = $items;
      }

      public function getFieldDefinition() {
        return $this->fieldDef;
      }

      public function getIterator(): \Traversable {
        return new \ArrayIterator($this->items);
      }
    };

    $entity->method('getFields')->willReturn(['body' => $fieldList]);

    return $entity;
  }

}
