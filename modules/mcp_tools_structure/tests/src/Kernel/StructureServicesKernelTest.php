<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_structure\Service\ContentTypeService;
use Drupal\mcp_tools_structure\Service\FieldService;
use Drupal\mcp_tools_structure\Service\TaxonomyService;

/**
 * Kernel tests for ContentTypeService, FieldService, and TaxonomyService.
 *
 * @group mcp_tools_structure
 */
final class StructureServicesKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'taxonomy',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_structure',
  ];

  private ContentTypeService $contentTypeService;

  private FieldService $fieldService;

  private TaxonomyService $taxonomyService;

  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mcp_tools']);
    $this->installConfig(['node']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('taxonomy_vocabulary');

    $this->contentTypeService = $this->container->get('mcp_tools_structure.content_type');
    $this->fieldService = $this->container->get('mcp_tools_structure.field');
    $this->taxonomyService = $this->container->get('mcp_tools_structure.taxonomy');

    // Structure services enforce write scope via AccessManager.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
  }

  // ==========================================================================
  // ContentTypeService Tests
  // ==========================================================================

  /**
   * Test listing content types when none exist.
   */
  public function testListContentTypesEmpty(): void {
    $result = $this->contentTypeService->listContentTypes();

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['data']['total']);
    $this->assertEmpty($result['data']['types']);
  }

  /**
   * Test listing content types after creation.
   */
  public function testListContentTypesAfterCreate(): void {
    $this->contentTypeService->createContentType('article', 'Article', ['create_body' => FALSE]);
    $this->contentTypeService->createContentType('page', 'Basic Page', ['create_body' => FALSE]);

    $result = $this->contentTypeService->listContentTypes();

    $this->assertTrue($result['success']);
    $this->assertSame(2, $result['data']['total']);
    $this->assertCount(2, $result['data']['types']);

    // Should be sorted by label.
    $this->assertSame('article', $result['data']['types'][0]['id']);
    $this->assertSame('page', $result['data']['types'][1]['id']);
  }

  /**
   * Test creating and deleting a content type.
   */
  public function testCreateAndDeleteContentType(): void {
    $create = $this->contentTypeService->createContentType('foo', 'Foo', [
      'create_body' => TRUE,
    ]);
    $this->assertTrue($create['success']);
    $this->assertSame('foo', $create['data']['id']);
    $this->assertSame('Foo', $create['data']['label']);

    $nodeType = $this->container->get('entity_type.manager')->getStorage('node_type')->load('foo');
    $this->assertNotNull($nodeType);

    $delete = $this->contentTypeService->deleteContentType('foo');
    $this->assertTrue($delete['success']);

    // Verify it's gone.
    $this->container->get('entity_type.manager')->getStorage('node_type')->resetCache(['foo']);
    $nodeType = $this->container->get('entity_type.manager')->getStorage('node_type')->load('foo');
    $this->assertNull($nodeType);
  }

  /**
   * Test getting content type details.
   */
  public function testGetContentType(): void {
    $this->contentTypeService->createContentType('test_type', 'Test Type', [
      'description' => 'A test content type',
      'create_body' => TRUE,
    ]);

    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $result = $this->contentTypeService->getContentType('test_type');

    $this->assertTrue($result['success']);
    $this->assertSame('test_type', $result['data']['id']);
    $this->assertSame('Test Type', $result['data']['label']);
    $this->assertSame('A test content type', $result['data']['description']);
    $this->assertIsArray($result['data']['fields']);
  }

  /**
   * Test getting a non-existent content type.
   */
  public function testGetContentTypeNotFound(): void {
    $result = $this->contentTypeService->getContentType('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test creating duplicate content type fails.
   */
  public function testCreateContentTypeDuplicate(): void {
    $this->contentTypeService->createContentType('duplicate', 'Duplicate', ['create_body' => FALSE]);

    $result = $this->contentTypeService->createContentType('duplicate', 'Duplicate 2', ['create_body' => FALSE]);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  /**
   * Test content type creation with invalid machine name.
   */
  public function testCreateContentTypeInvalidMachineName(): void {
    // Starts with number.
    $result = $this->contentTypeService->createContentType('123type', 'Invalid', ['create_body' => FALSE]);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid machine name', $result['error']);

    // Contains uppercase.
    $result = $this->contentTypeService->createContentType('MyType', 'Invalid', ['create_body' => FALSE]);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid machine name', $result['error']);

    // Contains special characters.
    $result = $this->contentTypeService->createContentType('my-type', 'Invalid', ['create_body' => FALSE]);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid machine name', $result['error']);
  }

  /**
   * Test content type machine name length limit.
   */
  public function testCreateContentTypeTooLongMachineName(): void {
    $longName = 'this_machine_name_is_way_too_long_for_drupal';
    $result = $this->contentTypeService->createContentType($longName, 'Long Name', ['create_body' => FALSE]);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('32 characters', $result['error']);
  }

  /**
   * Test deleting non-existent content type.
   */
  public function testDeleteContentTypeNotFound(): void {
    $result = $this->contentTypeService->deleteContentType('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test write operations require write scope.
   */
  public function testContentTypeWriteOperationsRequireWriteScope(): void {
    // Disable write scope.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    // Test create.
    $result = $this->contentTypeService->createContentType('test', 'Test', ['create_body' => FALSE]);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);

    // Re-enable to create a type for delete test.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
    $this->contentTypeService->createContentType('deleteme', 'Delete Me', ['create_body' => FALSE]);

    // Disable again.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    // Test delete.
    $result = $this->contentTypeService->deleteContentType('deleteme');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);
  }

  // ==========================================================================
  // FieldService Tests
  // ==========================================================================

  /**
   * Test adding and deleting a field.
   */
  public function testAddAndDeleteField(): void {
    $create = $this->contentTypeService->createContentType('bar', 'Bar', [
      'create_body' => FALSE,
    ]);
    $this->assertTrue($create['success']);

    // Field definition caches need to be cleared after creating a new bundle.
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    $add = $this->fieldService->addField('node', 'bar', 'subtitle', 'string', 'Subtitle');
    $this->assertTrue($add['success']);
    $this->assertSame('field_subtitle', $add['data']['field_name']);

    $fieldConfig = $this->container->get('entity_type.manager')->getStorage('field_config')->load('node.bar.field_subtitle');
    $this->assertNotNull($fieldConfig);

    $delete = $this->fieldService->deleteField('node', 'bar', 'subtitle');
    $this->assertTrue($delete['success']);
  }

  /**
   * Test field types are validated.
   *
   * Note: getFieldTypes() has a known issue with translation objects
   * in kernel tests. This test verifies field type validation instead.
   */
  public function testFieldTypeValidation(): void {
    $this->contentTypeService->createContentType('validatetype', 'Validate Type', ['create_body' => FALSE]);
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    // Valid field type should work.
    $result = $this->fieldService->addField('node', 'validatetype', 'valid', 'string', 'Valid');
    $this->assertTrue($result['success']);

    // Invalid field type should fail.
    $result = $this->fieldService->addField('node', 'validatetype', 'invalid', 'invalid_type', 'Invalid');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Unknown field type', $result['error']);
  }

  /**
   * Test adding field to non-existent bundle.
   */
  public function testAddFieldToNonExistentBundle(): void {
    $result = $this->fieldService->addField('node', 'nonexistent', 'myfield', 'string', 'My Field');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Missing bundle', $result['error']);
  }

  /**
   * Test adding field with invalid field type.
   */
  public function testAddFieldInvalidType(): void {
    $this->contentTypeService->createContentType('testbundle', 'Test Bundle', ['create_body' => FALSE]);
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    $result = $this->fieldService->addField('node', 'testbundle', 'myfield', 'nonexistent_type', 'My Field');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Unknown field type', $result['error']);
  }

  /**
   * Test adding duplicate field.
   */
  public function testAddFieldDuplicate(): void {
    $this->contentTypeService->createContentType('dupbundle', 'Dup Bundle', ['create_body' => FALSE]);
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    // Add first field.
    $result1 = $this->fieldService->addField('node', 'dupbundle', 'samefield', 'string', 'Same Field');
    $this->assertTrue($result1['success']);

    // Try to add same field again.
    $result2 = $this->fieldService->addField('node', 'dupbundle', 'samefield', 'string', 'Same Field 2');
    $this->assertFalse($result2['success']);
    $this->assertStringContainsString('already exists', $result2['error']);
  }

  /**
   * Test deleting non-existent field.
   */
  public function testDeleteFieldNotFound(): void {
    $this->contentTypeService->createContentType('fieldbundle', 'Field Bundle', ['create_body' => FALSE]);
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    $result = $this->fieldService->deleteField('node', 'fieldbundle', 'nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test field write operations require write scope.
   */
  public function testFieldWriteOperationsRequireWriteScope(): void {
    $this->contentTypeService->createContentType('scopetest', 'Scope Test', ['create_body' => FALSE]);
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    // Disable write scope.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    // Test add field.
    $result = $this->fieldService->addField('node', 'scopetest', 'testfield', 'string', 'Test');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);
  }

  // ==========================================================================
  // TaxonomyService Tests
  // ==========================================================================

  /**
   * Test listing vocabularies when none exist.
   */
  public function testListVocabulariesEmpty(): void {
    $result = $this->taxonomyService->listVocabularies();

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['data']['total']);
    $this->assertEmpty($result['data']['vocabularies']);
  }

  /**
   * Test creating and listing vocabularies.
   */
  public function testCreateAndListVocabularies(): void {
    $this->taxonomyService->createVocabulary('tags', 'Tags', 'Content tags');
    $this->taxonomyService->createVocabulary('categories', 'Categories', 'Content categories');

    $result = $this->taxonomyService->listVocabularies();

    $this->assertTrue($result['success']);
    $this->assertSame(2, $result['data']['total']);
    $this->assertCount(2, $result['data']['vocabularies']);

    // Should be sorted by label.
    $this->assertSame('categories', $result['data']['vocabularies'][0]['id']);
    $this->assertSame('tags', $result['data']['vocabularies'][1]['id']);
  }

  /**
   * Test getting vocabulary details.
   */
  public function testGetVocabulary(): void {
    $this->taxonomyService->createVocabulary('test_vocab', 'Test Vocabulary', 'A test vocabulary');

    $result = $this->taxonomyService->getVocabulary('test_vocab');

    $this->assertTrue($result['success']);
    $this->assertSame('test_vocab', $result['data']['id']);
    $this->assertSame('Test Vocabulary', $result['data']['label']);
    $this->assertSame('A test vocabulary', $result['data']['description']);
    $this->assertArrayHasKey('terms', $result['data']);
    $this->assertSame(0, $result['data']['total_terms']);
  }

  /**
   * Test getting non-existent vocabulary.
   */
  public function testGetVocabularyNotFound(): void {
    $result = $this->taxonomyService->getVocabulary('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test creating vocabulary with invalid machine name.
   */
  public function testCreateVocabularyInvalidMachineName(): void {
    // Starts with number.
    $result = $this->taxonomyService->createVocabulary('123vocab', 'Invalid', '');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid machine name', $result['error']);

    // Contains uppercase.
    $result = $this->taxonomyService->createVocabulary('MyVocab', 'Invalid', '');
    $this->assertFalse($result['success']);

    // Contains special characters.
    $result = $this->taxonomyService->createVocabulary('my-vocab', 'Invalid', '');
    $this->assertFalse($result['success']);
  }

  /**
   * Test creating duplicate vocabulary.
   */
  public function testCreateVocabularyDuplicate(): void {
    $this->taxonomyService->createVocabulary('duplicate', 'Duplicate', '');

    $result = $this->taxonomyService->createVocabulary('duplicate', 'Duplicate 2', '');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  /**
   * Test creating and listing terms.
   */
  public function testCreateAndListTerms(): void {
    $this->taxonomyService->createVocabulary('terms_test', 'Terms Test', '');

    $result1 = $this->taxonomyService->createTerm('terms_test', 'Term One');
    $this->assertTrue($result1['success']);
    $this->assertSame('Term One', $result1['data']['name']);

    $result2 = $this->taxonomyService->createTerm('terms_test', 'Term Two');
    $this->assertTrue($result2['success']);

    $vocab = $this->taxonomyService->getVocabulary('terms_test');
    $this->assertSame(2, $vocab['data']['total_terms']);
  }

  /**
   * Test creating term in non-existent vocabulary.
   */
  public function testCreateTermInNonExistentVocabulary(): void {
    $result = $this->taxonomyService->createTerm('nonexistent', 'Test Term');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test creating duplicate term.
   */
  public function testCreateTermDuplicate(): void {
    $this->taxonomyService->createVocabulary('dup_terms', 'Dup Terms', '');
    $this->taxonomyService->createTerm('dup_terms', 'Same Name');

    $result = $this->taxonomyService->createTerm('dup_terms', 'Same Name');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  /**
   * Test creating multiple terms at once.
   */
  public function testCreateTermsBatch(): void {
    $this->taxonomyService->createVocabulary('batch_test', 'Batch Test', '');

    $result = $this->taxonomyService->createTerms('batch_test', [
      'Term A',
      'Term B',
      'Term C',
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame(3, $result['data']['created_count']);
    $this->assertSame(0, $result['data']['error_count']);
  }

  /**
   * Test taxonomy write operations require write scope.
   */
  public function testTaxonomyWriteOperationsRequireWriteScope(): void {
    // Disable write scope.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    // Test create vocabulary.
    $result = $this->taxonomyService->createVocabulary('test', 'Test', '');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);

    // Re-enable to create a vocabulary for term tests.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
    $this->taxonomyService->createVocabulary('termvocab', 'Term Vocab', '');

    // Disable again.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    // Test create term.
    $result = $this->taxonomyService->createTerm('termvocab', 'Test Term');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);

    // Test create terms batch.
    $result = $this->taxonomyService->createTerms('termvocab', ['A', 'B']);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);
  }

}
