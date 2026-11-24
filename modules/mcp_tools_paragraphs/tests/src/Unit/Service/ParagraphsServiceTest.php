<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_paragraphs\Unit\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools\Service\FieldInput;
use Drupal\mcp_tools_paragraphs\Plugin\tool\Tool\AddParagraphField;
use Drupal\mcp_tools_paragraphs\Service\ParagraphsService;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tool\Attribute\Tool;

#[\PHPUnit\Framework\Attributes\CoversClass(ParagraphsService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_paragraphs')]
final class ParagraphsServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected EntityFieldManagerInterface $entityFieldManager;
  protected FieldTypePluginManagerInterface $fieldTypeManager;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $paragraphsTypeStorage;
  protected EntityStorageInterface $paragraphStorage;
  protected EntityStorageInterface $fieldConfigStorage;
  protected EntityStorageInterface $fieldStorageConfigStorage;

  /**
   * Field definitions returned by the entity field manager mock.
   *
   * @var array<string, \Drupal\Core\Field\FieldDefinitionInterface>
   */
  protected array $fieldDefinitions = [];

  /**
   * Field types whose module is off.
   *
   * @var string[]
   */
  protected array $missingFieldTypes = [];

  protected function setUp(): void {
    parent::setUp();

    // Skip tests if paragraphs module interfaces aren't available.
    if (!interface_exists(ParagraphsTypeInterface::class)) {
      $this->markTestSkipped('Paragraphs module is not installed.');
    }

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->fieldTypeManager = $this->createMock(FieldTypePluginManagerInterface::class);
    // Every field type is there, but the ones a test says are off.
    $this->fieldTypeManager->method('hasDefinition')
      ->willReturnCallback(fn (string $type): bool => !in_array($type, $this->missingFieldTypes, TRUE));
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
    $this->paragraphsTypeStorage = $this->createMock(EntityStorageInterface::class);
    $this->paragraphStorage = $this->createMock(EntityStorageInterface::class);
    $this->fieldConfigStorage = $this->createMock(EntityStorageInterface::class);
    $this->fieldStorageConfigStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['paragraphs_type', $this->paragraphsTypeStorage],
        ['paragraph', $this->paragraphStorage],
        ['field_config', $this->fieldConfigStorage],
        ['field_storage_config', $this->fieldStorageConfigStorage],
      ]);

    // Default: empty field definitions; tests override via the property —
    // a second method() stub would be shadowed by this one.
    $this->fieldDefinitions = [];
    $this->entityFieldManager->method('getFieldDefinitions')
      ->willReturnCallback(fn () => $this->fieldDefinitions);
  }

  protected function createService(): ParagraphsService {
    return new ParagraphsService(
      $this->entityTypeManager,
      $this->entityFieldManager,
      $this->accessManager,
      $this->auditLogger,
      $this->fieldTypeManager,
    );
  }

  protected function createMockParagraphType(string $id, string $label, string $description = ''): ParagraphsTypeInterface {
    $type = $this->createMock(ParagraphsTypeInterface::class);
    $type->method('id')->willReturn($id);
    $type->method('label')->willReturn($label);
    $type->method('getDescription')->willReturn($description);
    $type->method('getIconFile')->willReturn(NULL);
    return $type;
  }

  protected function createMockCountQuery(int $count): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn($count);
    return $query;
  }

  public function testListParagraphTypesEmpty(): void {
    $this->paragraphsTypeStorage->method('loadMultiple')->willReturn([]);

    $service = $this->createService();
    $result = $service->listParagraphTypes();

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['data']['total']);
    $this->assertSame([], $result['data']['types']);
  }

  public function testListParagraphTypesReturnsTypes(): void {
    $type1 = $this->createMockParagraphType('text', 'Text Block', 'Simple text block');
    $type2 = $this->createMockParagraphType('image', 'Image', 'Image paragraph');

    $this->paragraphsTypeStorage->method('loadMultiple')->willReturn([
      'text' => $type1,
      'image' => $type2,
    ]);

    $service = $this->createService();
    $result = $service->listParagraphTypes();

    $this->assertTrue($result['success']);
    $this->assertSame(2, $result['data']['total']);
    $this->assertCount(2, $result['data']['types']);
    $this->assertSame('text', $result['data']['types'][0]['id']);
    $this->assertSame('Text Block', $result['data']['types'][0]['label']);
    $this->assertSame('image', $result['data']['types'][1]['id']);
  }

  public function testGetParagraphTypeNotFound(): void {
    $this->paragraphsTypeStorage->method('load')
      ->with('missing')
      ->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getParagraphType('missing');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testGetParagraphTypeReturnsDetails(): void {
    $type = $this->createMockParagraphType('text', 'Text Block', 'Simple text');

    $this->paragraphsTypeStorage->method('load')
      ->with('text')
      ->willReturn($type);

    $service = $this->createService();
    $result = $service->getParagraphType('text');

    $this->assertTrue($result['success']);
    $this->assertSame('text', $result['data']['id']);
    $this->assertSame('Text Block', $result['data']['label']);
    $this->assertSame('Simple text', $result['data']['description']);
    $this->assertArrayHasKey('admin_path', $result['data']);
  }

  public function testCreateParagraphTypeRequiresWriteAccess(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createService();
    $result = $service->createParagraphType('test', 'Test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('denied', $result['error']);
  }

  public function testCreateParagraphTypeValidatesMachineName(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();

    // Invalid: starts with number.
    $result = $service->createParagraphType('1invalid', 'Test');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid machine name', $result['error']);

    // Invalid: uppercase.
    $result = $service->createParagraphType('Invalid', 'Test');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid machine name', $result['error']);

    // Invalid: too long.
    $result = $service->createParagraphType(str_repeat('a', 33), 'Test');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('32 characters', $result['error']);
  }

  public function testCreateParagraphTypeRejectsExisting(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $existingType = $this->createMockParagraphType('existing', 'Existing');
    $this->paragraphsTypeStorage->method('load')
      ->with('existing')
      ->willReturn($existingType);

    $service = $this->createService();
    $result = $service->createParagraphType('existing', 'Existing Type');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  public function testDeleteParagraphTypeRequiresWriteAccess(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createService();
    $result = $service->deleteParagraphType('test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('denied', $result['error']);
  }

  public function testDeleteParagraphTypeNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->paragraphsTypeStorage->method('load')
      ->with('missing')
      ->willReturn(NULL);

    $service = $this->createService();
    $result = $service->deleteParagraphType('missing');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testDeleteParagraphTypeBlockedWhenInUse(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $type = $this->createMockParagraphType('text', 'Text Block');
    $this->paragraphsTypeStorage->method('load')
      ->with('text')
      ->willReturn($type);

    $countQuery = $this->createMockCountQuery(5);
    $this->paragraphStorage->method('getQuery')->willReturn($countQuery);

    $service = $this->createService();
    $result = $service->deleteParagraphType('text');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('in use', $result['error']);
    $this->assertSame(5, $result['usage_count']);
  }

  public function testDeleteParagraphTypeAllowsForceDelete(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $type = $this->createMock(ParagraphsTypeInterface::class);
    $type->method('id')->willReturn('text');
    $type->method('label')->willReturn('Text Block');
    $type->expects($this->once())->method('delete');

    $this->paragraphsTypeStorage->method('load')
      ->with('text')
      ->willReturn($type);

    $countQuery = $this->createMockCountQuery(5);
    $this->paragraphStorage->method('getQuery')->willReturn($countQuery);

    $service = $this->createService();
    $result = $service->deleteParagraphType('text', TRUE);

    $this->assertTrue($result['success']);
    $this->assertSame(5, $result['data']['deleted_paragraphs']);
  }

  public function testAddFieldRequiresWriteAccess(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createService();
    $result = $service->addField('text', 'title', 'string');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('denied', $result['error']);
  }

  public function testAddFieldValidatesFieldName(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();

    // Invalid: uppercase after field_.
    $result = $service->addField('text', 'field_Invalid', 'string');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid field name', $result['error']);

    // Invalid: too long.
    $result = $service->addField('text', str_repeat('a', 30), 'string');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('32 characters', $result['error']);
  }

  public function testAddFieldValidatesFieldType(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();
    $result = $service->addField('text', 'title', 'unknown_type');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Unknown field type', $result['error']);
    $this->assertArrayHasKey('available_types', $result);
  }

  /**
   * A type whose module is off is refused before anything is made.
   *
   * The paragraphs copy of the type table took it, and Drupal failed on
   * the field storage. The answer names the types the site has.
   */
  public function testAddFieldRefusesATypeWhoseModuleIsOff(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->missingFieldTypes = ['list_string'];
    $this->paragraphsTypeStorage->expects($this->never())->method('load');

    $result = $this->createService()->addField('text', 'style', 'list_string');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not available on this site', $result['error']);
    $this->assertNotContains('list_string', $result['available_types']);
    $this->assertContains('timestamp', $result['available_types'], 'The paragraphs tools do not take timestamp, which the structure tools do.');
  }

  /**
   * The tool's text names every field type the service takes.
   *
   * It named 6 of them and "etc.", as the structure tool's text did before
   * a model picked text_long where plain long text was asked for.
   */
  public function testTheToolNamesEveryFieldType(): void {
    $attribute = (new \ReflectionClass(AddParagraphField::class))->getAttributes(Tool::class)[0]->getArguments();
    $text = $attribute['input_definitions']['field_type']->getDescription()->getUntranslatedString();
    $missing = array_values(array_filter(array_keys(FieldInput::TYPES), static fn(string $type): bool => !preg_match('/(?<![a-z_])' . $type . '(?![a-z_])/', $text)));
    $this->assertSame([], $missing, 'mcp_paragraphs_add_field does not name: ' . implode(', ', $missing));
  }

  public function testAddFieldChecksParagraphTypeExists(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->paragraphsTypeStorage->method('load')
      ->with('missing')
      ->willReturn(NULL);

    $service = $this->createService();
    $result = $service->addField('missing', 'title', 'string');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('does not exist', $result['error']);
  }

  public function testAddFieldChecksFieldAlreadyExists(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $type = $this->createMockParagraphType('text', 'Text Block');
    $this->paragraphsTypeStorage->method('load')
      ->with('text')
      ->willReturn($type);

    $existingField = $this->createMock(FieldDefinitionInterface::class);
    $this->fieldDefinitions = ['field_title' => $existingField];

    $service = $this->createService();
    $result = $service->addField('text', 'title', 'string');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  public function testDeleteFieldRequiresWriteAccess(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createService();
    $result = $service->deleteField('text', 'title');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('denied', $result['error']);
  }

  public function testDeleteFieldNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->fieldConfigStorage->method('load')
      ->with('paragraph.text.field_title')
      ->willReturn(NULL);

    $service = $this->createService();
    $result = $service->deleteField('text', 'title');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testFormatAllowedValuesWithAssociativeArray(): void {
    $service = $this->createService();

    $method = new \ReflectionMethod($service, 'formatAllowedValues');

    $input = ['opt1' => 'Option 1', 'opt2' => 'Option 2'];
    $result = $method->invoke($service, $input);

    $this->assertSame($input, $result);
  }

  public function testFormatAllowedValuesWithIndexedArray(): void {
    $service = $this->createService();

    $method = new \ReflectionMethod($service, 'formatAllowedValues');

    $input = ['Red', 'Green', 'Blue'];
    $result = $method->invoke($service, $input);

    $this->assertArrayHasKey('red', $result);
    $this->assertArrayHasKey('green', $result);
    $this->assertArrayHasKey('blue', $result);
    $this->assertSame('Red', $result['red']);
  }

  /**
   * A list of numbers keeps its numbers as keys. It used to be a crash.
   */
  public function testFormatAllowedValuesWithNumbers(): void {
    $service = $this->createService();

    $method = new \ReflectionMethod($service, 'formatAllowedValues');

    $this->assertSame([1 => '1', 2 => '2', 5 => '5'], $method->invoke($service, [1, 2, 5]));
  }

  /**
   * Options as {"value", "label"} items, as the structure tool takes them.
   */
  public function testFormatAllowedValuesWithItems(): void {
    $method = new \ReflectionMethod($this->createService(), 'formatAllowedValues');
    $this->assertSame(['d' => 'Draft', 'f' => 'Final'], $method->invoke($this->createService(), [['value' => 'd', 'label' => 'Draft'], ['value' => 'f', 'label' => 'Final']]));
  }

  public function testGetFieldsForBundleFiltersBaseFields(): void {
    // Create fresh entityFieldManager mock for this specific test.
    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);

    $fieldStorage = $this->createMock(FieldStorageDefinitionInterface::class);
    $fieldStorage->method('getCardinality')->willReturn(1);

    $customField = $this->createMock(FieldDefinitionInterface::class);
    $customField->method('getType')->willReturn('string');
    $customField->method('getLabel')->willReturn('Title');
    $customField->method('isRequired')->willReturn(FALSE);
    $customField->method('getDescription')->willReturn('');
    $customField->method('getFieldStorageDefinition')->willReturn($fieldStorage);

    $baseField = $this->createMock(FieldDefinitionInterface::class);

    $entityFieldManager->method('getFieldDefinitions')
      ->with('paragraph', 'text')
      ->willReturn([
        'id' => $baseField,
        'uuid' => $baseField,
        'field_title' => $customField,
      ]);

    $service = new ParagraphsService(
      $this->entityTypeManager,
      $entityFieldManager,
      $this->accessManager,
      $this->auditLogger,
      $this->fieldTypeManager,
    );

    $method = new \ReflectionMethod($service, 'getFieldsForBundle');
    $result = $method->invoke($service, 'text');

    $this->assertCount(1, $result);
    $this->assertSame('field_title', $result[0]['name']);
  }

}
