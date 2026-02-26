<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_pathauto\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_pathauto\Service\PathautoService;
use Drupal\pathauto\AliasCleanerInterface;
use Drupal\pathauto\PathautoGeneratorInterface;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_pathauto\Service\PathautoService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_pathauto')]
final class PathautoServiceTest extends UnitTestCase {

  private function createService(array $overrides = []): PathautoService {
    return new PathautoService(
      $overrides['entity_type_manager'] ?? $this->createMock(EntityTypeManagerInterface::class),
      $overrides['pathauto_generator'] ?? $this->createMock(PathautoGeneratorInterface::class),
      $overrides['alias_cleaner'] ?? $this->createMock(AliasCleanerInterface::class),
      $overrides['access_manager'] ?? $this->createMock(AccessManager::class),
      $overrides['audit_logger'] ?? $this->createMock(AuditLogger::class),
    );
  }

  public function testListPatternsReturnsAll(): void {
    $pattern1 = $this->createMock(\Drupal\Core\Entity\EntityInterface::class);
    $pattern1->method('id')->willReturn('article_pattern');
    $pattern1->method('label')->willReturn('Article');
    $pattern1->method('get')->willReturnMap([
      ['type', 'canonical_entities:node'],
      ['pattern', 'content/[node:title]'],
      ['weight', 0],
      ['status', TRUE],
      ['selection_criteria', []],
    ]);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn(['article_pattern']);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn(['article_pattern' => $pattern1]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('pathauto_pattern')->willReturn($storage);

    $service = $this->createService(['entity_type_manager' => $entityTypeManager]);
    $result = $service->listPatterns();

    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['total']);
  }

  public function testGetPatternNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('missing')->willReturn(NULL);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('pathauto_pattern')->willReturn($storage);

    $service = $this->createService(['entity_type_manager' => $entityTypeManager]);
    $result = $service->getPattern('missing');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testCreatePatternRequiresWriteAccess(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(FALSE);
    $accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createService(['access_manager' => $accessManager]);
    $result = $service->createPattern('test', 'Test', '[node:title]', 'node');

    $this->assertFalse($result['success']);
  }

  public function testCreatePatternRejectsExistingId(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $existing = $this->createMock(\Drupal\Core\Entity\EntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('existing_id')->willReturn($existing);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('pathauto_pattern')->willReturn($storage);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'access_manager' => $accessManager,
    ]);
    $result = $service->createPattern('existing_id', 'Test', '[node:title]', 'node');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  public function testDeletePatternRequiresWriteAccess(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(FALSE);
    $accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createService(['access_manager' => $accessManager]);
    $result = $service->deletePattern('test');

    $this->assertFalse($result['success']);
  }

  public function testGenerateAliasesRejectsInvalidEntityType(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getDefinition')->with('invalid', FALSE)->willReturn(NULL);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'access_manager' => $accessManager,
    ]);
    $result = $service->generateAliases('invalid');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid entity type', $result['error']);
  }

  public function testGenerateAliasesNoEntitiesFound(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $entityTypeDef = $this->createMock(EntityTypeInterface::class);
    $entityTypeDef->method('getKey')->with('bundle')->willReturn('type');

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getDefinition')->with('node', FALSE)->willReturn($entityTypeDef);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'access_manager' => $accessManager,
    ]);
    $result = $service->generateAliases('node');

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['data']['processed']);
    $this->assertStringContainsString('No entities found', $result['data']['message']);
  }

}
