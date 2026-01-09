<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Unit\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\mcp_tools_analysis\Service\FieldAnalyzer;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for FieldAnalyzer.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_analysis\Service\FieldAnalyzer::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_analysis')]
final class FieldAnalyzerTest extends UnitTestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private EntityFieldManagerInterface $entityFieldManager;
  private EntityTypeBundleInfoInterface $bundleInfo;
  private FieldAnalyzer $analyzer;

  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->bundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);

    $this->analyzer = new FieldAnalyzer(
      $this->entityTypeManager,
      $this->entityFieldManager,
      $this->bundleInfo,
    );
  }

  public function testAnalyzeFieldUsageReturnsStructuredResult(): void {
    $this->bundleInfo->method('getBundleInfo')->willReturn([]);

    $result = $this->analyzer->analyzeFieldUsage('node');

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('entity_type', $result['data']);
    $this->assertArrayHasKey('bundles', $result['data']);
    $this->assertSame('node', $result['data']['entity_type']);
  }

  public function testAnalyzeFieldUsageWithBundles(): void {
    $this->bundleInfo->method('getBundleInfo')->with('node')->willReturn([
      'article' => ['label' => 'Article'],
      'page' => ['label' => 'Basic Page'],
    ]);

    $bodyField = $this->createMock(FieldDefinitionInterface::class);
    $bodyField->method('getName')->willReturn('body');
    $bodyField->method('getLabel')->willReturn('Body');
    $bodyField->method('getType')->willReturn('text_with_summary');
    $bodyField->method('isRequired')->willReturn(FALSE);
    $bodyField->method('getFieldStorageDefinition')->willReturn(
      new class {
        public function getCardinality(): int {
          return 1;
        }
      }
    );

    $this->entityFieldManager->method('getFieldDefinitions')
      ->willReturn(['body' => $bodyField]);

    $result = $this->analyzer->analyzeFieldUsage('node');

    $this->assertTrue($result['success']);
    $this->assertCount(2, $result['data']['bundles']);
  }

  public function testAnalyzeFieldUsageFiltersByBundle(): void {
    $this->bundleInfo->method('getBundleInfo')->with('node')->willReturn([
      'article' => ['label' => 'Article'],
      'page' => ['label' => 'Basic Page'],
    ]);

    $this->entityFieldManager->method('getFieldDefinitions')
      ->with('node', 'article')
      ->willReturn([]);

    $result = $this->analyzer->analyzeFieldUsage('node', 'article');

    $this->assertTrue($result['success']);
    $this->assertCount(1, $result['data']['bundles']);
    $this->assertArrayHasKey('article', $result['data']['bundles']);
  }

  public function testFindUnusedFieldsReturnsStructuredResult(): void {
    $this->bundleInfo->method('getBundleInfo')->willReturn([]);

    $result = $this->analyzer->findUnusedFields('node');

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('entity_type', $result['data']);
    $this->assertArrayHasKey('unused_fields', $result['data']);
  }

}
