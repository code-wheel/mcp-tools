<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_entity_clone\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_entity_clone\Service\EntityCloneService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_entity_clone\Service\EntityCloneService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_entity_clone')]
final class EntityCloneServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected EntityFieldManagerInterface $entityFieldManager;
  protected ConfigFactoryInterface $configFactory;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;

  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);

    // Default: allow writes.
    $this->accessManager->method('canWrite')->willReturn(TRUE);
  }

  protected function createService(): EntityCloneService {
    return new EntityCloneService(
      $this->entityTypeManager,
      $this->entityFieldManager,
      $this->configFactory,
      $this->accessManager,
      $this->auditLogger,
    );
  }

  protected function createMockEntity(
    string $id,
    string $label,
    bool $hasStatus = TRUE,
    bool $fieldable = TRUE,
  ): ContentEntityInterface&FieldableEntityInterface {
    $entity = $this->createMock(FieldableEntityInterface::class);

    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getKey')->with('label')->willReturn('title');

    $entity->method('id')->willReturn($id);
    $entity->method('uuid')->willReturn('uuid-' . $id);
    $entity->method('label')->willReturn($label);
    $entity->method('getEntityType')->willReturn($entityType);
    $entity->method('hasField')->willReturnCallback(function ($field) use ($hasStatus) {
      if ($field === 'title') {
        return TRUE;
      }
      if ($field === 'status') {
        return $hasStatus;
      }
      return FALSE;
    });
    $entity->method('getFieldDefinitions')->willReturn([]);

    return $entity;
  }

  public function testCloneEntityRequiresWriteAccess(): void {
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createService();
    $result = $service->cloneEntity('node', 1);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('denied', $result['error']);
  }

  public function testCloneEntityRejectsUnsupportedType(): void {
    $service = $this->createService();
    $result = $service->cloneEntity('user', 1);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not supported', $result['error']);
    $this->assertArrayHasKey('supported_types', $result);
  }

  public function testCloneEntityReturnsErrorWhenEntityNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(999)->willReturn(NULL);

    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $service = $this->createService();
    $result = $service->cloneEntity('node', 999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testCloneEntitySuccess(): void {
    $sourceEntity = $this->createMockEntity('1', 'Original Title');
    $clonedEntity = $this->createMockEntity('2', 'Original Title (Clone)');

    $sourceEntity->method('createDuplicate')->willReturn($clonedEntity);
    $clonedEntity->method('set')->willReturnSelf();
    $clonedEntity->expects($this->once())->method('save');
    $clonedEntity->method('get')->willReturn($this->createMock(FieldItemListInterface::class));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('1')->willReturn($sourceEntity);

    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $this->auditLogger->expects($this->once())
      ->method('logSuccess')
      ->with('clone_entity', 'node', '2', $this->anything());

    $service = $this->createService();
    $result = $service->cloneEntity('node', '1');

    $this->assertTrue($result['success']);
    $this->assertSame('1', $result['data']['source_id']);
    $this->assertSame('2', $result['data']['clone_id']);
  }

  public function testCloneEntityWithCustomPrefixSuffix(): void {
    $sourceEntity = $this->createMockEntity('1', 'Article');
    $clonedEntity = $this->createMockEntity('2', 'Copy of Article - v2');

    $sourceEntity->method('createDuplicate')->willReturn($clonedEntity);

    // Capture the set call to verify label modification.
    $capturedLabel = NULL;
    $clonedEntity->method('set')->willReturnCallback(function ($field, $value) use (&$capturedLabel, $clonedEntity) {
      if ($field === 'title') {
        $capturedLabel = $value;
      }
      return $clonedEntity;
    });
    $clonedEntity->method('save')->willReturn(1);
    $clonedEntity->method('get')->willReturn($this->createMock(FieldItemListInterface::class));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('1')->willReturn($sourceEntity);

    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $service = $this->createService();
    $result = $service->cloneEntity('node', '1', [
      'title_prefix' => 'Copy of ',
      'title_suffix' => ' - v2',
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('Copy of Article - v2', $capturedLabel);
  }

  public function testCloneEntitySetsUnpublished(): void {
    $sourceEntity = $this->createMockEntity('1', 'Title', hasStatus: TRUE);
    $clonedEntity = $this->createMockEntity('2', 'Title (Clone)', hasStatus: TRUE);

    $sourceEntity->method('createDuplicate')->willReturn($clonedEntity);

    // Track if status was set to 0.
    $statusSet = NULL;
    $clonedEntity->method('set')->willReturnCallback(function ($field, $value) use (&$statusSet, $clonedEntity) {
      if ($field === 'status') {
        $statusSet = $value;
      }
      return $clonedEntity;
    });
    $clonedEntity->method('save')->willReturn(1);
    $clonedEntity->method('get')->willReturn($this->createMock(FieldItemListInterface::class));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('1')->willReturn($sourceEntity);

    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $service = $this->createService();
    $service->cloneEntity('node', '1');

    $this->assertSame(0, $statusSet);
  }

  public function testCloneEntityHandlesException(): void {
    $sourceEntity = $this->createMockEntity('1', 'Title');
    $sourceEntity->method('createDuplicate')
      ->willThrowException(new \Exception('Clone failed'));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('1')->willReturn($sourceEntity);

    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $this->auditLogger->expects($this->once())
      ->method('logFailure')
      ->with('clone_entity', 'node', '1', $this->anything());

    $service = $this->createService();
    $result = $service->cloneEntity('node', '1');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Clone failed', $result['error']);
  }

  public function testCloneWithReferencesRequiresWriteAccess(): void {
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createService();
    $result = $service->cloneWithReferences('node', 1, ['field_ref']);

    $this->assertFalse($result['success']);
  }

  public function testCloneWithReferencesRejectsUnsupportedType(): void {
    $service = $this->createService();
    $result = $service->cloneWithReferences('user', 1, ['field_ref']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not supported', $result['error']);
  }

  public function testGetCloneableTypesReturnsAvailableTypes(): void {
    $nodeType = $this->createMock(EntityTypeInterface::class);
    $nodeType->method('getLabel')->willReturn('Content');
    $nodeType->method('getBundleEntityType')->willReturn('node_type');

    $mediaType = $this->createMock(EntityTypeInterface::class);
    $mediaType->method('getLabel')->willReturn('Media');
    $mediaType->method('getBundleEntityType')->willReturn('media_type');

    $this->entityTypeManager->method('getDefinition')
      ->willReturnCallback(function ($type, $throw) use ($nodeType, $mediaType) {
        return match ($type) {
          'node' => $nodeType,
          'media' => $mediaType,
          default => NULL,
        };
      });

    // Mock bundle storage.
    $bundleStorage = $this->createMock(EntityStorageInterface::class);
    $bundleStorage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')
      ->willReturn($bundleStorage);

    $service = $this->createService();
    $result = $service->getCloneableTypes();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('types', $result['data']);
    $this->assertArrayHasKey('total', $result['data']);
  }

  public function testGetCloneSettingsReturnsFieldInfo(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturn(NULL);

    $this->configFactory->method('get')->willReturn($config);

    $fieldStorage = $this->createMock(FieldStorageDefinitionInterface::class);
    $fieldStorage->method('getCardinality')->willReturn(-1);

    $refField = $this->createMock(FieldDefinitionInterface::class);
    $refField->method('getType')->willReturn('entity_reference');
    $refField->method('getLabel')->willReturn('Related Content');
    $refField->method('getSetting')->with('target_type')->willReturn('node');
    $refField->method('getFieldStorageDefinition')->willReturn($fieldStorage);

    $paragraphField = $this->createMock(FieldDefinitionInterface::class);
    $paragraphField->method('getType')->willReturn('entity_reference_revisions');
    $paragraphField->method('getLabel')->willReturn('Content Blocks');
    $paragraphField->method('getSetting')->with('target_type')->willReturn('paragraph');
    $paragraphField->method('getFieldStorageDefinition')->willReturn($fieldStorage);

    $this->entityFieldManager->method('getFieldDefinitions')
      ->with('node', 'article')
      ->willReturn([
        'field_related' => $refField,
        'field_content' => $paragraphField,
      ]);

    $service = $this->createService();
    $result = $service->getCloneSettings('node', 'article');

    $this->assertTrue($result['success']);
    $this->assertSame('node', $result['data']['entity_type']);
    $this->assertSame('article', $result['data']['bundle']);
    $this->assertNotEmpty($result['data']['reference_fields']);
    $this->assertNotEmpty($result['data']['paragraph_fields']);
    $this->assertTrue($result['data']['has_paragraphs']);
  }

  public function testGetCloneSettingsFiltersNonFieldPrefix(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturn(NULL);
    $this->configFactory->method('get')->willReturn($config);

    $baseField = $this->createMock(FieldDefinitionInterface::class);
    $baseField->method('getType')->willReturn('entity_reference');

    $this->entityFieldManager->method('getFieldDefinitions')
      ->with('node', 'page')
      ->willReturn([
        'uid' => $baseField,
        'nid' => $baseField,
      ]);

    $service = $this->createService();
    $result = $service->getCloneSettings('node', 'page');

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['reference_fields']);
    $this->assertEmpty($result['data']['paragraph_fields']);
  }

  public function testSupportedEntityTypes(): void {
    $expected = ['node', 'media', 'paragraph', 'taxonomy_term', 'block_content', 'menu_link_content'];

    $service = $this->createService();

    // Test each supported type doesn't return "not supported" error.
    foreach ($expected as $type) {
      $storage = $this->createMock(EntityStorageInterface::class);
      $storage->method('load')->willReturn(NULL);
      $this->entityTypeManager->method('getStorage')->with($type)->willReturn($storage);

      $result = $service->cloneEntity($type, 1);
      // Should fail with "not found", not "not supported".
      $this->assertStringContainsString('not found', $result['error'] ?? '');
    }
  }

}
