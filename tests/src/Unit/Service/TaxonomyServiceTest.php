<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\TaxonomyService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\mcp_tools\Service\TaxonomyService
 * @group mcp_tools
 */
final class TaxonomyServiceTest extends UnitTestCase {

  /**
   * @covers ::searchTerms
   */
  public function testSearchTermsRequiresMinimumLength(): void {
    $service = new TaxonomyService($this->createMock(EntityTypeManagerInterface::class));
    $result = $service->searchTerms('a');
    $this->assertArrayHasKey('error', $result);
  }

  /**
   * @covers ::getTerms
   */
  public function testGetTermsReturnsErrorWhenVocabularyMissing(): void {
    $vocabStorage = $this->createMock(EntityStorageInterface::class);
    $vocabStorage->method('load')->with('tags')->willReturn(NULL);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['taxonomy_term', $this->createMock(EntityStorageInterface::class)],
      ['taxonomy_vocabulary', $vocabStorage],
    ]);

    $service = new TaxonomyService($entityTypeManager);
    $result = $service->getTerms('tags');

    $this->assertArrayHasKey('error', $result);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::buildHierarchy
   */
  public function testBuildHierarchyNestsChildrenUnderFirstParent(): void {
    $service = new TaxonomyService($this->createMock(EntityTypeManagerInterface::class));

    $method = new \ReflectionMethod($service, 'buildHierarchy');
    $method->setAccessible(TRUE);

    $flat = [
      ['tid' => 1, 'name' => 'Root', 'parent_ids' => []],
      ['tid' => 2, 'name' => 'Child', 'parent_ids' => [1]],
    ];

    $tree = $method->invoke($service, $flat);
    $this->assertCount(1, $tree);
    $this->assertSame(1, $tree[0]['tid']);
    $this->assertCount(1, $tree[0]['children']);
    $this->assertSame(2, $tree[0]['children'][0]['tid']);
  }

}

