<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_paragraphs\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_paragraphs\Service\ParagraphsService;

/**
 * Kernel tests for ParagraphsService.
 *
 * These tests require the paragraphs module to be installed.
 * They are run as part of the contrib integration CI matrix.
 *
 * @group mcp_tools_paragraphs
 * @requires module paragraphs
 */
final class ParagraphsServiceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'file',
    'entity_reference_revisions',
    'paragraphs',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_paragraphs',
  ];

  /**
   * The paragraphs service under test.
   */
  private ParagraphsService $paragraphsService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mcp_tools']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('file');

    $this->paragraphsService = $this->container->get('mcp_tools_paragraphs.paragraphs');

    // Enable write scope for testing write operations.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
  }

  /**
   * Test listing paragraph types when none exist.
   */
  public function testListParagraphTypesEmpty(): void {
    $result = $this->paragraphsService->listParagraphTypes();

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['data']['total']);
    $this->assertEmpty($result['data']['types']);
  }

  /**
   * Test creating and listing paragraph types.
   */
  public function testCreateAndListParagraphType(): void {
    // Create a paragraph type.
    $result = $this->paragraphsService->createParagraphType(
      'text_block',
      'Text Block',
      'A simple text paragraph'
    );

    $this->assertTrue($result['success']);
    $this->assertSame('text_block', $result['data']['id']);
    $this->assertSame('Text Block', $result['data']['label']);
    $this->assertStringContainsString('created successfully', $result['data']['message']);

    // Verify it shows up in the list.
    $list = $this->paragraphsService->listParagraphTypes();
    $this->assertTrue($list['success']);
    $this->assertSame(1, $list['data']['total']);
    $this->assertSame('text_block', $list['data']['types'][0]['id']);
  }

  /**
   * Test getting a specific paragraph type.
   */
  public function testGetParagraphType(): void {
    // Create a paragraph type first.
    $this->paragraphsService->createParagraphType('hero', 'Hero Section');

    $result = $this->paragraphsService->getParagraphType('hero');

    $this->assertTrue($result['success']);
    $this->assertSame('hero', $result['data']['id']);
    $this->assertSame('Hero Section', $result['data']['label']);
    $this->assertArrayHasKey('fields', $result['data']);
  }

  /**
   * Test getting a non-existent paragraph type.
   */
  public function testGetParagraphTypeNotFound(): void {
    $result = $this->paragraphsService->getParagraphType('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test creating duplicate paragraph type fails.
   */
  public function testCreateDuplicateParagraphTypeFails(): void {
    $this->paragraphsService->createParagraphType('test', 'Test');

    $result = $this->paragraphsService->createParagraphType('test', 'Test Again');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  /**
   * Test invalid machine name validation.
   */
  public function testInvalidMachineNameValidation(): void {
    // Test uppercase.
    $result = $this->paragraphsService->createParagraphType('TestType', 'Test');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid machine name', $result['error']);

    // Test starting with number.
    $result = $this->paragraphsService->createParagraphType('1test', 'Test');
    $this->assertFalse($result['success']);

    // Test special characters.
    $result = $this->paragraphsService->createParagraphType('test-type', 'Test');
    $this->assertFalse($result['success']);
  }

  /**
   * Test deleting a paragraph type.
   */
  public function testDeleteParagraphType(): void {
    $this->paragraphsService->createParagraphType('to_delete', 'To Delete');

    $result = $this->paragraphsService->deleteParagraphType('to_delete');

    $this->assertTrue($result['success']);
    $this->assertStringContainsString('deleted successfully', $result['data']['message']);

    // Verify it's gone.
    $get = $this->paragraphsService->getParagraphType('to_delete');
    $this->assertFalse($get['success']);
  }

  /**
   * Test deleting non-existent paragraph type fails.
   */
  public function testDeleteNonExistentParagraphTypeFails(): void {
    $result = $this->paragraphsService->deleteParagraphType('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test adding a field to a paragraph type.
   */
  public function testAddFieldToParagraphType(): void {
    $this->paragraphsService->createParagraphType('with_field', 'With Field');

    // Clear caches after creating the bundle.
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    $result = $this->paragraphsService->addField('with_field', 'title', 'string', [
      'label' => 'Title',
      'required' => TRUE,
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('field_title', $result['data']['field_name']);
    $this->assertSame('with_field', $result['data']['bundle']);

    // Verify field exists.
    $type = $this->paragraphsService->getParagraphType('with_field');
    $this->assertTrue($type['success']);
    $this->assertNotEmpty($type['data']['fields']);

    $fieldNames = array_column($type['data']['fields'], 'name');
    $this->assertContains('field_title', $fieldNames);
  }

  /**
   * Test adding field to non-existent paragraph type fails.
   */
  public function testAddFieldToNonExistentBundleFails(): void {
    $result = $this->paragraphsService->addField('nonexistent', 'title', 'string');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('does not exist', $result['error']);
  }

  /**
   * Test adding field with invalid type fails.
   */
  public function testAddFieldInvalidTypeFails(): void {
    $this->paragraphsService->createParagraphType('test_bundle', 'Test');

    $result = $this->paragraphsService->addField('test_bundle', 'field', 'invalid_type');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Unknown field type', $result['error']);
    $this->assertArrayHasKey('available_types', $result);
  }

  /**
   * Test deleting a field from a paragraph type.
   */
  public function testDeleteFieldFromParagraphType(): void {
    $this->paragraphsService->createParagraphType('delete_field_test', 'Test');
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    $this->paragraphsService->addField('delete_field_test', 'to_remove', 'string');

    $result = $this->paragraphsService->deleteField('delete_field_test', 'to_remove');

    $this->assertTrue($result['success']);
    $this->assertStringContainsString('deleted', $result['data']['message']);
  }

  /**
   * Test write operations require write scope.
   */
  public function testWriteOperationsRequireWriteScope(): void {
    // Set read-only scope.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    $result = $this->paragraphsService->createParagraphType('test', 'Test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('denied', strtolower($result['error']));
  }

}
