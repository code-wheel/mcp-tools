<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_structure\Service\TaxonomyManagementService;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for TaxonomyManagementService.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_structure\Service\TaxonomyManagementService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_structure')]
final class TaxonomyManagementServiceTest extends UnitTestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private AccessManager $accessManager;
  private AuditLogger $auditLogger;
  private TaxonomyManagementService $service;

  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);

    $this->service = new TaxonomyManagementService(
      $this->entityTypeManager,
      $this->accessManager,
      $this->auditLogger,
    );
  }

  public function testListVocabulariesReturnsEmptyList(): void {
    $vocabStorage = $this->createMock(EntityStorageInterface::class);
    $vocabStorage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($type) use ($vocabStorage) {
        if ($type === 'taxonomy_vocabulary') {
          return $vocabStorage;
        }
        return $this->createMock(EntityStorageInterface::class);
      });

    $result = $this->service->listVocabularies();

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['data']['total']);
    $this->assertEmpty($result['data']['vocabularies']);
  }

  public function testListVocabulariesWithResults(): void {
    $vocabulary = $this->createMock(VocabularyInterface::class);
    $vocabulary->method('id')->willReturn('tags');
    $vocabulary->method('label')->willReturn('Tags');
    $vocabulary->method('getDescription')->willReturn('Free tagging vocabulary.');

    $vocabStorage = $this->createMock(EntityStorageInterface::class);
    $vocabStorage->method('loadMultiple')->willReturn(['tags' => $vocabulary]);

    $termQuery = $this->createMock(QueryInterface::class);
    $termQuery->method('accessCheck')->willReturnSelf();
    $termQuery->method('condition')->willReturnSelf();
    $termQuery->method('count')->willReturnSelf();
    $termQuery->method('execute')->willReturn(5);

    $termStorage = $this->createMock(EntityStorageInterface::class);
    $termStorage->method('getQuery')->willReturn($termQuery);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($type) use ($vocabStorage, $termStorage) {
        return match ($type) {
          'taxonomy_vocabulary' => $vocabStorage,
          'taxonomy_term' => $termStorage,
          default => $this->createMock(EntityStorageInterface::class),
        };
      });

    $result = $this->service->listVocabularies();

    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['total']);
    $this->assertSame('tags', $result['data']['vocabularies'][0]['id']);
    $this->assertSame('Tags', $result['data']['vocabularies'][0]['label']);
    $this->assertSame(5, $result['data']['vocabularies'][0]['term_count']);
  }

  public function testGetVocabularyNotFound(): void {
    $vocabStorage = $this->createMock(EntityStorageInterface::class);
    $vocabStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $this->entityTypeManager->method('getStorage')
      ->with('taxonomy_vocabulary')
      ->willReturn($vocabStorage);

    $result = $this->service->getVocabulary('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testGetVocabularyReturnsDetails(): void {
    $vocabulary = $this->createMock(VocabularyInterface::class);
    $vocabulary->method('id')->willReturn('tags');
    $vocabulary->method('label')->willReturn('Tags');
    $vocabulary->method('getDescription')->willReturn('Free tagging.');

    $term = $this->createMock(TermInterface::class);
    $term->method('id')->willReturn(1);
    $term->method('getName')->willReturn('Drupal');
    $term->method('getDescription')->willReturn('');
    $term->method('getWeight')->willReturn(0);

    $vocabStorage = $this->createMock(EntityStorageInterface::class);
    $vocabStorage->method('load')->with('tags')->willReturn($vocabulary);

    $termQuery = $this->createMock(QueryInterface::class);
    $termQuery->method('accessCheck')->willReturnSelf();
    $termQuery->method('condition')->willReturnSelf();
    $termQuery->method('sort')->willReturnSelf();
    $termQuery->method('range')->willReturnSelf();
    $termQuery->method('count')->willReturnSelf();
    $termQuery->method('execute')->willReturnOnConsecutiveCalls([1], 1);

    $termStorage = $this->createMock(TermStorageInterface::class);
    $termStorage->method('getQuery')->willReturn($termQuery);
    $termStorage->method('loadMultiple')->willReturn([1 => $term]);
    $termStorage->method('loadParents')->willReturn([]);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($type) use ($vocabStorage, $termStorage) {
        return match ($type) {
          'taxonomy_vocabulary' => $vocabStorage,
          'taxonomy_term' => $termStorage,
          default => $this->createMock(EntityStorageInterface::class),
        };
      });

    $result = $this->service->getVocabulary('tags');

    $this->assertTrue($result['success']);
    $this->assertSame('tags', $result['data']['id']);
    $this->assertSame('Tags', $result['data']['label']);
    $this->assertCount(1, $result['data']['terms']);
  }

  public function testCreateVocabularyAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $result = $this->service->createVocabulary('test', 'Test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('denied', $result['error']);
  }

  public function testCreateVocabularyInvalidMachineName(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $result = $this->service->createVocabulary('Invalid-Name', 'Test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid machine name', $result['error']);
  }

  public function testCreateVocabularyNameTooLong(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $longName = str_repeat('a', 33);
    $result = $this->service->createVocabulary($longName, 'Test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('32 characters', $result['error']);
  }

  public function testCreateVocabularyAlreadyExists(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $existing = $this->createMock(VocabularyInterface::class);
    $vocabStorage = $this->createMock(EntityStorageInterface::class);
    $vocabStorage->method('load')->with('tags')->willReturn($existing);

    $this->entityTypeManager->method('getStorage')
      ->with('taxonomy_vocabulary')
      ->willReturn($vocabStorage);

    $result = $this->service->createVocabulary('tags', 'Tags');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  public function testCreateVocabularySuccess(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $vocabulary = $this->createMock(VocabularyInterface::class);

    $vocabStorage = $this->createMock(EntityStorageInterface::class);
    $vocabStorage->method('load')->with('tags')->willReturn(NULL);
    $vocabStorage->method('create')->willReturn($vocabulary);

    $this->entityTypeManager->method('getStorage')
      ->with('taxonomy_vocabulary')
      ->willReturn($vocabStorage);

    $this->auditLogger->expects($this->once())
      ->method('logSuccess')
      ->with('create_vocabulary', 'taxonomy_vocabulary', 'tags', $this->anything());

    $result = $this->service->createVocabulary('tags', 'Tags', 'Free tagging vocabulary.');

    $this->assertTrue($result['success']);
    $this->assertSame('tags', $result['data']['id']);
    $this->assertSame('Tags', $result['data']['label']);
  }

  public function testCreateTermAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $result = $this->service->createTerm('tags', 'Test Term');

    $this->assertFalse($result['success']);
  }

  public function testCreateTermVocabularyNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $vocabStorage = $this->createMock(EntityStorageInterface::class);
    $vocabStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($type) use ($vocabStorage) {
        if ($type === 'taxonomy_vocabulary') {
          return $vocabStorage;
        }
        return $this->createMock(EntityStorageInterface::class);
      });

    $result = $this->service->createTerm('nonexistent', 'Test Term');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testCreateTermDuplicate(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $vocabulary = $this->createMock(VocabularyInterface::class);
    $vocabStorage = $this->createMock(EntityStorageInterface::class);
    $vocabStorage->method('load')->willReturn($vocabulary);

    $termQuery = $this->createMock(QueryInterface::class);
    $termQuery->method('accessCheck')->willReturnSelf();
    $termQuery->method('condition')->willReturnSelf();
    $termQuery->method('execute')->willReturn([5]);

    $termStorage = $this->createMock(EntityStorageInterface::class);
    $termStorage->method('getQuery')->willReturn($termQuery);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($type) use ($vocabStorage, $termStorage) {
        return match ($type) {
          'taxonomy_vocabulary' => $vocabStorage,
          'taxonomy_term' => $termStorage,
          default => $this->createMock(EntityStorageInterface::class),
        };
      });

    $result = $this->service->createTerm('tags', 'Drupal');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
    $this->assertSame(5, $result['existing_tid']);
  }

  public function testCreateTermSuccess(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $vocabulary = $this->createMock(VocabularyInterface::class);
    $vocabStorage = $this->createMock(EntityStorageInterface::class);
    $vocabStorage->method('load')->willReturn($vocabulary);

    $term = $this->createMock(TermInterface::class);
    $term->method('id')->willReturn(10);

    $termQuery = $this->createMock(QueryInterface::class);
    $termQuery->method('accessCheck')->willReturnSelf();
    $termQuery->method('condition')->willReturnSelf();
    $termQuery->method('execute')->willReturn([]);

    $termStorage = $this->createMock(EntityStorageInterface::class);
    $termStorage->method('getQuery')->willReturn($termQuery);
    $termStorage->method('create')->willReturn($term);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($type) use ($vocabStorage, $termStorage) {
        return match ($type) {
          'taxonomy_vocabulary' => $vocabStorage,
          'taxonomy_term' => $termStorage,
          default => $this->createMock(EntityStorageInterface::class),
        };
      });

    $this->auditLogger->expects($this->once())
      ->method('logSuccess')
      ->with('create_term', 'taxonomy_term', '10', $this->anything());

    $result = $this->service->createTerm('tags', 'PHP', ['description' => 'A programming language']);

    $this->assertTrue($result['success']);
    $this->assertSame(10, $result['data']['tid']);
    $this->assertSame('PHP', $result['data']['name']);
  }

  public function testCreateTermsVocabularyNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $vocabStorage = $this->createMock(EntityStorageInterface::class);
    $vocabStorage->method('load')->willReturn(NULL);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($type) use ($vocabStorage) {
        if ($type === 'taxonomy_vocabulary') {
          return $vocabStorage;
        }
        return $this->createMock(EntityStorageInterface::class);
      });

    $result = $this->service->createTerms('nonexistent', ['Term1', 'Term2']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testCreateTermsBulkCreation(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $vocabulary = $this->createMock(VocabularyInterface::class);
    $vocabStorage = $this->createMock(EntityStorageInterface::class);
    $vocabStorage->method('load')->willReturn($vocabulary);

    $term = $this->createMock(TermInterface::class);
    $term->method('id')->willReturn(1);

    $termQuery = $this->createMock(QueryInterface::class);
    $termQuery->method('accessCheck')->willReturnSelf();
    $termQuery->method('condition')->willReturnSelf();
    $termQuery->method('execute')->willReturn([]);

    $termStorage = $this->createMock(EntityStorageInterface::class);
    $termStorage->method('getQuery')->willReturn($termQuery);
    $termStorage->method('create')->willReturn($term);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($type) use ($vocabStorage, $termStorage) {
        return match ($type) {
          'taxonomy_vocabulary' => $vocabStorage,
          'taxonomy_term' => $termStorage,
          default => $this->createMock(EntityStorageInterface::class),
        };
      });

    $result = $this->service->createTerms('tags', ['PHP', 'Drupal', 'MCP']);

    $this->assertTrue($result['success']);
    $this->assertSame(3, $result['data']['created_count']);
    $this->assertSame(0, $result['data']['error_count']);
  }

}
