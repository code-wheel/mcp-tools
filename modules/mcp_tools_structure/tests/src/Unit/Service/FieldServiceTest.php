<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Unit\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_structure\Service\FieldService;
use Drupal\node\NodeTypeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for FieldService.
 *
 * @coversDefaultClass \Drupal\mcp_tools_structure\Service\FieldService
 * @group mcp_tools_structure
 */
class FieldServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected EntityFieldManagerInterface $entityFieldManager;
  protected FieldTypePluginManagerInterface $fieldTypeManager;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $nodeTypeStorage;
  protected EntityStorageInterface $fieldStorageConfigStorage;
  protected EntityStorageInterface $fieldConfigStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->fieldTypeManager = $this->createMock(FieldTypePluginManagerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);

    $this->nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $this->fieldStorageConfigStorage = $this->createMock(EntityStorageInterface::class);
    $this->fieldConfigStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['node_type', $this->nodeTypeStorage],
        ['field_storage_config', $this->fieldStorageConfigStorage],
        ['field_config', $this->fieldConfigStorage],
      ]);
  }

  /**
   * Creates a FieldService instance.
   */
  protected function createService(): FieldService {
    return new FieldService(
      $this->entityTypeManager,
      $this->entityFieldManager,
      $this->fieldTypeManager,
      $this->accessManager,
      $this->auditLogger
    );
  }

  /**
   * @covers ::getFieldTypes
   */
  public function testGetFieldTypes(): void {
    $service = $this->createService();
    $result = $service->getFieldTypes();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('types', $result['data']);
    $this->assertContains('string', $result['data']['types']);
    $this->assertContains('text_long', $result['data']['types']);
    $this->assertContains('entity_reference', $result['data']['types']);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->addField('node', 'article', 'body', 'text_long');

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldInvalidName(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();
    $result = $service->addField('node', 'article', 'field_Invalid-Name', 'text');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid field name', $result['error']);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldNameTooLong(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();
    $result = $service->addField('node', 'article', str_repeat('a', 33), 'text');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('32 characters', $result['error']);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldInvalidType(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();
    $result = $service->addField('node', 'article', 'test', 'invalid_type');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Unknown field type', $result['error']);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldBundleNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->nodeTypeStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->addField('node', 'nonexistent', 'test', 'string');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('does not exist', $result['error']);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldAlreadyExists(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $nodeType = $this->createMock(NodeTypeInterface::class);
    $this->nodeTypeStorage->method('load')->willReturn($nodeType);

    $fieldDef = $this->createMock(FieldDefinitionInterface::class);
    $this->entityFieldManager->method('getFieldDefinitions')
      ->with('node', 'article')
      ->willReturn(['field_test' => $fieldDef]);

    $service = $this->createService();
    $result = $service->addField('node', 'article', 'test', 'string');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  /**
   * @covers ::deleteField
   */
  public function testDeleteFieldAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->deleteField('node', 'article', 'body');

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::deleteField
   */
  public function testDeleteFieldNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->fieldConfigStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->deleteField('node', 'article', 'nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::listFields
   */
  public function testListFieldsEmpty(): void {
    $this->entityFieldManager->method('getFieldDefinitions')
      ->with('node', 'article')
      ->willReturn([]);

    $service = $this->createService();
    $result = $service->listFields('node', 'article');

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['fields']);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldNormalizesName(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $nodeType = $this->createMock(NodeTypeInterface::class);
    $this->nodeTypeStorage->method('load')->willReturn($nodeType);
    $this->entityFieldManager->method('getFieldDefinitions')->willReturn([]);
    $this->fieldStorageConfigStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    // Field name without field_ prefix - fails on invalid type but name normalized.
    $result = $service->addField('node', 'article', 'test', 'invalid');

    $this->assertStringContainsString('Unknown field type', $result['error']);
  }

}
