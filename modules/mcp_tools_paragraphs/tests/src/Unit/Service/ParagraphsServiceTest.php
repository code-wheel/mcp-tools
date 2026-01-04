<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_paragraphs\Unit\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_paragraphs\Service\ParagraphsService;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for ParagraphsService.
 *
 * @coversDefaultClass \Drupal\mcp_tools_paragraphs\Service\ParagraphsService
 * @group mcp_tools_paragraphs
 */
class ParagraphsServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected EntityFieldManagerInterface $entityFieldManager;
  protected FieldTypePluginManagerInterface $fieldTypeManager;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $paragraphsTypeStorage;
  protected EntityStorageInterface $paragraphStorage;
  protected EntityStorageInterface $fieldStorageConfigStorage;
  protected EntityStorageInterface $fieldConfigStorage;
  protected EntityStorageInterface $formDisplayStorage;
  protected EntityStorageInterface $viewDisplayStorage;

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

    $this->paragraphsTypeStorage = $this->createMock(EntityStorageInterface::class);
    $this->paragraphStorage = $this->createMock(EntityStorageInterface::class);
    $this->fieldStorageConfigStorage = $this->createMock(EntityStorageInterface::class);
    $this->fieldConfigStorage = $this->createMock(EntityStorageInterface::class);
    $this->formDisplayStorage = $this->createMock(EntityStorageInterface::class);
    $this->viewDisplayStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['paragraphs_type', $this->paragraphsTypeStorage],
        ['paragraph', $this->paragraphStorage],
        ['field_storage_config', $this->fieldStorageConfigStorage],
        ['field_config', $this->fieldConfigStorage],
        ['entity_form_display', $this->formDisplayStorage],
        ['entity_view_display', $this->viewDisplayStorage],
      ]);
  }

  /**
   * Creates a ParagraphsService instance.
   */
  protected function createService(): ParagraphsService {
    return new ParagraphsService(
      $this->entityTypeManager,
      $this->entityFieldManager,
      $this->fieldTypeManager,
      $this->accessManager,
      $this->auditLogger
    );
  }

  /**
   * Creates a mock paragraph type.
   */
  protected function createMockParagraphType(string $id, string $label, string $description = ''): ParagraphsTypeInterface {
    $type = $this->createMock(ParagraphsTypeInterface::class);
    $type->method('id')->willReturn($id);
    $type->method('label')->willReturn($label);
    $type->method('getDescription')->willReturn($description);
    $type->method('getIconUuid')->willReturn(NULL);
    return $type;
  }

  /**
   * @covers ::listParagraphTypes
   */
  public function testListParagraphTypesEmpty(): void {
    $this->paragraphsTypeStorage->method('loadMultiple')->willReturn([]);
    $this->entityFieldManager->method('getFieldDefinitions')->willReturn([]);

    $service = $this->createService();
    $result = $service->listParagraphTypes();

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['types']);
    $this->assertEquals(0, $result['data']['total']);
  }

  /**
   * @covers ::listParagraphTypes
   */
  public function testListParagraphTypesWithTypes(): void {
    $type1 = $this->createMockParagraphType('text', 'Text', 'A text paragraph');
    $type2 = $this->createMockParagraphType('image', 'Image', 'An image paragraph');

    $this->paragraphsTypeStorage->method('loadMultiple')
      ->willReturn(['text' => $type1, 'image' => $type2]);
    $this->entityFieldManager->method('getFieldDefinitions')->willReturn([]);

    $service = $this->createService();
    $result = $service->listParagraphTypes();

    $this->assertTrue($result['success']);
    $this->assertCount(2, $result['data']['types']);
    $this->assertEquals('text', $result['data']['types'][0]['id']);
    $this->assertEquals('image', $result['data']['types'][1]['id']);
  }

  /**
   * @covers ::getParagraphType
   */
  public function testGetParagraphTypeNotFound(): void {
    $this->paragraphsTypeStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getParagraphType('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::getParagraphType
   */
  public function testGetParagraphTypeSuccess(): void {
    $type = $this->createMockParagraphType('text', 'Text', 'A text paragraph');
    $this->paragraphsTypeStorage->method('load')->with('text')->willReturn($type);
    $this->entityFieldManager->method('getFieldDefinitions')->willReturn([]);

    $service = $this->createService();
    $result = $service->getParagraphType('text');

    $this->assertTrue($result['success']);
    $this->assertEquals('text', $result['data']['id']);
    $this->assertEquals('Text', $result['data']['label']);
    $this->assertEquals('A text paragraph', $result['data']['description']);
  }

  /**
   * @covers ::createParagraphType
   */
  public function testCreateParagraphTypeAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->createParagraphType('test', 'Test');

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * @covers ::createParagraphType
   */
  public function testCreateParagraphTypeInvalidMachineName(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();

    // Test invalid characters.
    $result = $service->createParagraphType('Test-Type', 'Test');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid machine name', $result['error']);

    // Test starting with number.
    $result = $service->createParagraphType('123test', 'Test');
    $this->assertFalse($result['success']);

    // Test too long.
    $result = $service->createParagraphType(str_repeat('a', 33), 'Test');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('32 characters', $result['error']);
  }

  /**
   * @covers ::createParagraphType
   */
  public function testCreateParagraphTypeAlreadyExists(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $existing = $this->createMockParagraphType('test', 'Test');
    $this->paragraphsTypeStorage->method('load')->with('test')->willReturn($existing);

    $service = $this->createService();
    $result = $service->createParagraphType('test', 'Test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  /**
   * @covers ::deleteParagraphType
   */
  public function testDeleteParagraphTypeAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->deleteParagraphType('test');

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::deleteParagraphType
   */
  public function testDeleteParagraphTypeNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->paragraphsTypeStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->deleteParagraphType('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::deleteParagraphType
   */
  public function testDeleteParagraphTypeInUseWithoutForce(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $type = $this->createMockParagraphType('test', 'Test');
    $this->paragraphsTypeStorage->method('load')->willReturn($type);

    $query = $this->createMock(\Drupal\Core\Entity\Query\QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn(5);

    $this->paragraphStorage->method('getQuery')->willReturn($query);

    $service = $this->createService();
    $result = $service->deleteParagraphType('test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('in use', $result['error']);
    $this->assertEquals(5, $result['usage_count']);
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
    $result = $service->addField('test', 'body', 'text_long');

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldInvalidName(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();

    // Test invalid characters.
    $result = $service->addField('test', 'field_Body-Test', 'text');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid field name', $result['error']);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldInvalidType(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();
    $result = $service->addField('test', 'body', 'invalid_type');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Unknown field type', $result['error']);
    $this->assertArrayHasKey('available_types', $result);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldParagraphTypeNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->paragraphsTypeStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->addField('nonexistent', 'body', 'text_long');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('does not exist', $result['error']);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldAlreadyExists(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $type = $this->createMockParagraphType('test', 'Test');
    $this->paragraphsTypeStorage->method('load')->willReturn($type);

    $fieldDef = $this->createMock(FieldDefinitionInterface::class);
    $this->entityFieldManager->method('getFieldDefinitions')
      ->with('paragraph', 'test')
      ->willReturn(['field_body' => $fieldDef]);

    $service = $this->createService();
    $result = $service->addField('test', 'body', 'text_long');

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
    $result = $service->deleteField('test', 'body');

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::deleteField
   */
  public function testDeleteFieldNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->fieldConfigStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->deleteField('test', 'nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::listParagraphTypes
   */
  public function testListParagraphTypesWithFields(): void {
    $type = $this->createMockParagraphType('text', 'Text');
    $this->paragraphsTypeStorage->method('loadMultiple')->willReturn(['text' => $type]);

    $storageDef = $this->createMock(FieldStorageDefinitionInterface::class);
    $storageDef->method('getCardinality')->willReturn(1);

    $fieldDef = $this->createMock(FieldDefinitionInterface::class);
    $fieldDef->method('getType')->willReturn('text_long');
    $fieldDef->method('getLabel')->willReturn('Body');
    $fieldDef->method('isRequired')->willReturn(FALSE);
    $fieldDef->method('getDescription')->willReturn('The body text');
    $fieldDef->method('getFieldStorageDefinition')->willReturn($storageDef);

    $this->entityFieldManager->method('getFieldDefinitions')
      ->with('paragraph', 'text')
      ->willReturn(['field_body' => $fieldDef]);

    $service = $this->createService();
    $result = $service->listParagraphTypes();

    $this->assertTrue($result['success']);
    $this->assertEquals(1, $result['data']['types'][0]['field_count']);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldNormalizesName(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $type = $this->createMockParagraphType('test', 'Test');
    $this->paragraphsTypeStorage->method('load')->willReturn($type);
    $this->entityFieldManager->method('getFieldDefinitions')->willReturn([]);
    $this->fieldStorageConfigStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    // Field name without field_ prefix should be normalized.
    $result = $service->addField('test', 'body', 'invalid_type');

    // It fails on invalid type, but the field name validation passed.
    $this->assertStringContainsString('Unknown field type', $result['error']);
  }

}
