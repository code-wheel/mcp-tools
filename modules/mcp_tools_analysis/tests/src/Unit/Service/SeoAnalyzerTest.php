<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Unit\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\mcp_tools_analysis\Service\SeoAnalyzer;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for SeoAnalyzer.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_analysis\Service\SeoAnalyzer::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_analysis')]
final class SeoAnalyzerTest extends UnitTestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private ModuleHandlerInterface $moduleHandler;
  private SeoAnalyzer $analyzer;

  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->analyzer = new SeoAnalyzer($this->entityTypeManager, $this->moduleHandler);
  }

  public function testAnalyzeSeoEntityNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(999)->willReturn(NULL);
    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $result = $this->analyzer->analyzeSeo('node', 999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testAnalyzeSeoShortTitle(): void {
    $entity = $this->createMockEntity('Short', '');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($entity);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $result = $this->analyzer->analyzeSeo('node', 1);

    $this->assertTrue($result['success']);
    $this->assertLessThan(100, $result['data']['seo_score']);

    $titleIssue = array_filter($result['data']['issues'], fn($i) => $i['type'] === 'title_short');
    $this->assertNotEmpty($titleIssue);
  }

  public function testAnalyzeSeoLongTitle(): void {
    $longTitle = str_repeat('A', 70);
    $entity = $this->createMockEntity($longTitle, '');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($entity);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $result = $this->analyzer->analyzeSeo('node', 1);

    $titleIssue = array_filter($result['data']['issues'], fn($i) => $i['type'] === 'title_long');
    $this->assertNotEmpty($titleIssue);
  }

  public function testAnalyzeSeoOptimalTitle(): void {
    $optimalTitle = str_repeat('A', 45);
    $entity = $this->createMockEntity($optimalTitle, '');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($entity);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $result = $this->analyzer->analyzeSeo('node', 1);

    $titleIssues = array_filter($result['data']['issues'], fn($i) => str_starts_with($i['type'], 'title_'));
    $this->assertEmpty($titleIssues);
  }

  public function testAnalyzeSeoWithoutMetatagModule(): void {
    $entity = $this->createMockEntity('Test Title', '');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($entity);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);
    $this->moduleHandler->method('moduleExists')->with('metatag')->willReturn(FALSE);

    $result = $this->analyzer->analyzeSeo('node', 1);

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('seo_score', $result['data']);
  }

  /**
   * Creates a mock entity with title and body.
   */
  private function createMockEntity(string $title, string $body): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('label')->willReturn($title);
    $entity->method('hasField')->willReturn(FALSE);

    // Create body field.
    $fieldDef = $this->createMock(FieldDefinitionInterface::class);
    $fieldDef->method('getType')->willReturn('text_with_summary');

    $fieldItem = new \stdClass();
    $fieldItem->value = $body;

    // Create an anonymous class that implements IteratorAggregate to allow
    // iteration over field items (PHPUnit cannot mock getIterator).
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
