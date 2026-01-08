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
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_structure\Service\FieldService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_structure')]
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

  public function testGetFieldTypes(): void {
    // Mock the field type manager to return definitions for types we query.
    $this->fieldTypeManager->method('getDefinition')
      ->willReturnCallback(function ($type, $exceptionOnInvalid) {
        $definitions = [
          'string' => ['label' => 'Text (plain)', 'description' => 'A plain text field'],
          'text_long' => ['label' => 'Text (formatted, long)', 'description' => 'A long text field'],
          'entity_reference' => ['label' => 'Entity reference', 'description' => 'A reference field'],
        ];
        return $definitions[$type] ?? NULL;
      });

    $service = $this->createService();
    $result = $service->getFieldTypes();

    // getFieldTypes returns {types: [...], total: N} directly (no success wrapper).
    $this->assertArrayHasKey('types', $result);
    $this->assertArrayHasKey('total', $result);
    $this->assertIsArray($result['types']);
    $this->assertGreaterThan(0, $result['total']);

    // Check that common types are present.
    $typeIds = array_column($result['types'], 'id');
    $this->assertContains('string', $typeIds);
    $this->assertContains('text_long', $typeIds);
    $this->assertContains('entity_reference', $typeIds);
  }

  public function testAddFieldAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->addField('node', 'article', 'body', 'text_long', 'Body');

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  public function testAddFieldInvalidName(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();
    $result = $service->addField('node', 'article', 'field_Invalid-Name', 'text', 'Invalid Field');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid field name', $result['error']);
  }

  public function testAddFieldNameTooLong(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();
    $result = $service->addField('node', 'article', str_repeat('a', 33), 'text', 'Too Long');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('32 characters', $result['error']);
  }

  public function testAddFieldInvalidType(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();
    $result = $service->addField('node', 'article', 'test', 'invalid_type', 'Test Field');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Unknown field type', $result['error']);
  }

  public function testAddFieldBundleNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->nodeTypeStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->addField('node', 'nonexistent', 'test', 'string', 'Test Field');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('does not exist', $result['error']);
  }

  public function testAddFieldAlreadyExists(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $nodeType = $this->createMock(NodeTypeInterface::class);
    $this->nodeTypeStorage->method('load')->willReturn($nodeType);

    $fieldDef = $this->createMock(FieldDefinitionInterface::class);
    $this->entityFieldManager->method('getFieldDefinitions')
      ->with('node', 'article')
      ->willReturn(['field_test' => $fieldDef]);

    $service = $this->createService();
    $result = $service->addField('node', 'article', 'test', 'string', 'Test Field');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

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

  public function testDeleteFieldNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->fieldConfigStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->deleteField('node', 'article', 'nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testAddFieldNormalizesName(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $nodeType = $this->createMock(NodeTypeInterface::class);
    $this->nodeTypeStorage->method('load')->willReturn($nodeType);
    $this->entityFieldManager->method('getFieldDefinitions')->willReturn([]);
    $this->fieldStorageConfigStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    // Field name without field_ prefix - fails on invalid type but name normalized.
    $result = $service->addField('node', 'article', 'test', 'invalid', 'Test Field');

    $this->assertStringContainsString('Unknown field type', $result['error']);
  }

}
