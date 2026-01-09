<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\mcp_tools_analysis\Service\FieldAnalyzer;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for FieldAnalyzer.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_analysis\Service\FieldAnalyzer::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_analysis')]
final class FieldAnalyzerTest extends UnitTestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private FieldAnalyzer $analyzer;

  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->analyzer = new FieldAnalyzer($this->entityTypeManager);
  }

  public function testFindUnusedFieldsReturnsStructuredResult(): void {
    $fieldConfigStorage = $this->createMock(EntityStorageInterface::class);
    $fieldConfigStorage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')
      ->with('field_config')
      ->willReturn($fieldConfigStorage);

    $result = $this->analyzer->findUnusedFields();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('unused_fields', $result['data']);
    $this->assertArrayHasKey('unused_count', $result['data']);
    $this->assertSame(0, $result['data']['unused_count']);
  }

  public function testFindUnusedFieldsIgnoresBaseFields(): void {
    // Create a field config that doesn't start with 'field_'.
    $baseField = $this->createMock(FieldConfigInterface::class);
    $baseField->method('getTargetEntityTypeId')->willReturn('node');
    $baseField->method('getTargetBundle')->willReturn('article');
    $baseField->method('getName')->willReturn('title');
    $baseField->method('getType')->willReturn('string');
    $baseField->method('getLabel')->willReturn('Title');

    $fieldConfigStorage = $this->createMock(EntityStorageInterface::class);
    $fieldConfigStorage->method('loadMultiple')->willReturn(['title' => $baseField]);

    $this->entityTypeManager->method('getStorage')
      ->with('field_config')
      ->willReturn($fieldConfigStorage);

    $result = $this->analyzer->findUnusedFields();

    $this->assertTrue($result['success']);
    // Base fields are skipped, so unused_count should be 0.
    $this->assertSame(0, $result['data']['unused_count']);
  }

  public function testFindUnusedFieldsDetectsUnusedField(): void {
    // Create a field config that starts with 'field_'.
    $customField = $this->createMock(FieldConfigInterface::class);
    $customField->method('getTargetEntityTypeId')->willReturn('node');
    $customField->method('getTargetBundle')->willReturn('article');
    $customField->method('getName')->willReturn('field_unused');
    $customField->method('getType')->willReturn('string');
    $customField->method('getLabel')->willReturn('Unused Field');

    $fieldConfigStorage = $this->createMock(EntityStorageInterface::class);
    $fieldConfigStorage->method('loadMultiple')->willReturn(['field_unused' => $customField]);

    // Mock the node query that returns 0 results (unused).
    $nodeQuery = $this->createMock(QueryInterface::class);
    $nodeQuery->method('condition')->willReturnSelf();
    $nodeQuery->method('accessCheck')->willReturnSelf();
    $nodeQuery->method('range')->willReturnSelf();
    $nodeQuery->method('count')->willReturnSelf();
    $nodeQuery->method('execute')->willReturn(0);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($nodeQuery);

    $this->entityTypeManager->method('getStorage')->willReturnMap([
      ['field_config', $fieldConfigStorage],
      ['node', $nodeStorage],
    ]);

    $result = $this->analyzer->findUnusedFields();

    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['unused_count']);
    $this->assertNotEmpty($result['data']['unused_fields']);
    $this->assertSame('field_unused', $result['data']['unused_fields'][0]['field_name']);
  }

  public function testFindUnusedFieldsSkipsUsedField(): void {
    // Create a field config that starts with 'field_'.
    $customField = $this->createMock(FieldConfigInterface::class);
    $customField->method('getTargetEntityTypeId')->willReturn('node');
    $customField->method('getTargetBundle')->willReturn('article');
    $customField->method('getName')->willReturn('field_used');
    $customField->method('getType')->willReturn('string');
    $customField->method('getLabel')->willReturn('Used Field');

    $fieldConfigStorage = $this->createMock(EntityStorageInterface::class);
    $fieldConfigStorage->method('loadMultiple')->willReturn(['field_used' => $customField]);

    // Mock the node query that returns results (field is used).
    $nodeQuery = $this->createMock(QueryInterface::class);
    $nodeQuery->method('condition')->willReturnSelf();
    $nodeQuery->method('accessCheck')->willReturnSelf();
    $nodeQuery->method('range')->willReturnSelf();
    $nodeQuery->method('count')->willReturnSelf();
    $nodeQuery->method('execute')->willReturn(5);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($nodeQuery);

    $this->entityTypeManager->method('getStorage')->willReturnMap([
      ['field_config', $fieldConfigStorage],
      ['node', $nodeStorage],
    ]);

    $result = $this->analyzer->findUnusedFields();

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['data']['unused_count']);
  }

}
