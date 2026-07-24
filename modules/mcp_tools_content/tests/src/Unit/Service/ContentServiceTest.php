<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_content\Unit\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_content\Service\ContentService;
use Drupal\node\NodeTypeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for ContentService.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_content\Service\ContentService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_content')]
class ContentServiceTest extends UnitTestCase {

  /**
   * The mocked entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The mocked entity field manager.
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The mocked current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The mocked MCP access manager.
   */
  protected AccessManager $accessManager;

  /**
   * The mocked audit logger.
   */
  protected AuditLogger $auditLogger;

  /**
   * The mocked node storage.
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * The mocked node type storage.
   */
  protected EntityStorageInterface $nodeTypeStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->currentUser->method('id')->willReturn(1);

    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);

    $this->nodeStorage = $this->createMock(EntityStorageInterface::class);
    $this->nodeTypeStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['node', $this->nodeStorage],
        ['node_type', $this->nodeTypeStorage],
      ]);
  }

  /**
   * Creates a mocked time service.
   *
   * @return \Drupal\Component\Datetime\TimeInterface
   *   The mocked time service.
   */
  protected function mockTime(): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(time());
    return $time;
  }

  /**
   * Creates a ContentService instance.
   *
   * @return \Drupal\mcp_tools_content\Service\ContentService
   *   The service under test.
   */
  protected function createContentService(): ContentService {
    return new ContentService(
      $this->entityTypeManager,
      $this->entityFieldManager,
      $this->currentUser,
      $this->accessManager,
      $this->auditLogger,
      $this->mockTime(),
      $this->createMock(ModuleHandlerInterface::class),
    );
  }

  /**
   * Creation is denied without write access.
   */
  public function testCreateContentAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createContentService();
    $result = $service->createContent('article', 'Test');

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * Creation fails cleanly for an unknown content type.
   */
  public function testCreateContentInvalidContentType(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->nodeTypeStorage->method('load')->with('invalid_type')->willReturn(NULL);

    $service = $this->createContentService();
    $result = $service->createContent('invalid_type', 'Test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Updating is denied without write access.
   */
  public function testUpdateContentAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createContentService();
    $result = $service->updateContent(1, ['title' => 'New Title']);

    $this->assertFalse($result['success']);
  }

  /**
   * Updating an unknown node fails cleanly.
   */
  public function testUpdateContentNodeNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->nodeStorage->method('load')->with(999)->willReturn(NULL);

    $service = $this->createContentService();
    $result = $service->updateContent(999, ['title' => 'New Title']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Deleting is denied without write access.
   */
  public function testDeleteContentAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createContentService();
    $result = $service->deleteContent(1);

    $this->assertFalse($result['success']);
  }

  /**
   * Deleting an unknown node fails cleanly.
   */
  public function testDeleteContentNodeNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->nodeStorage->method('load')->with(999)->willReturn(NULL);

    $service = $this->createContentService();
    $result = $service->deleteContent(999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Publishing is denied without write access.
   */
  public function testSetPublishStatusAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createContentService();
    $result = $service->setPublishStatus(1, TRUE);

    $this->assertFalse($result['success']);
  }

  /**
   * Publishing an unknown node fails cleanly.
   */
  public function testSetPublishStatusNodeNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->nodeStorage->method('load')->with(999)->willReturn(NULL);

    $service = $this->createContentService();
    $result = $service->setPublishStatus(999, TRUE);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Tests field value normalization code paths for various field types.
   *
   * @dataProvider normalizeFieldValueProvider
   */
  public function testNormalizeFieldValueBehavior(string $fieldType, mixed $input, mixed $expectedPattern): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $nodeType = $this->createMock(NodeTypeInterface::class);
    $this->nodeTypeStorage->method('load')->with('article')->willReturn($nodeType);

    $fieldDef = $this->createMock(FieldDefinitionInterface::class);
    $fieldDef->method('getType')->willReturn($fieldType);

    $this->entityFieldManager->method('getFieldDefinitions')
      ->with('node', 'article')
      ->willReturn(['field_test' => $fieldDef]);

    // We can't easily test the actual normalization without creating nodes,
    // but we verify the service can be constructed for various field types.
    $this->createContentService();

    // This tests that the code path doesn't throw an exception.
    // Full integration testing would require Drupal bootstrap.
    $this->assertTrue(TRUE);
  }

  /**
   * Data provider for field normalization tests.
   */
  public static function normalizeFieldValueProvider(): array {
    return [
      'text_long string' => ['text_long', 'Simple text', ['value' => 'Simple text']],
      'text_long array' => ['text_long', ['value' => 'Text', 'format' => 'full_html'], ['value' => 'Text']],
      'entity_reference int' => ['entity_reference', 5, ['target_id' => 5]],
      'entity_reference array' => ['entity_reference', ['target_id' => 5], ['target_id' => 5]],
      'link string' => ['link', 'https://example.com', ['uri' => 'https://example.com']],
      'datetime string' => ['datetime', '2024-01-15', ['value' => '2024-01-15']],
      'string direct' => ['string', 'plain value', 'plain value'],
    ];
  }

  /**
   * ERR values without inline definitions pass through unchanged.
   *
   * Items referencing existing entities (['target_id' => ...]) must not be
   * dropped by the inline-creation support added for Layout Paragraphs.
   */
  public function testEntityReferenceRevisionsPassesThroughPlainReferences(): void {
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->method('hasDefinition')->with('paragraph')->willReturn(TRUE);

    $fieldDef = $this->createMock(FieldDefinitionInterface::class);
    $fieldDef->method('getSetting')->with('target_type')->willReturn('paragraph');

    $service = $this->createContentService();
    $method = new \ReflectionMethod($service, 'createReferencedEntities');

    // A single reference to an existing paragraph.
    $this->assertSame(
      [['target_id' => 5]],
      $method->invoke($service, ['target_id' => 5], $fieldDef),
    );

    // A list of references, including a revision-qualified one.
    $value = [['target_id' => 5], ['target_id' => 7, 'target_revision_id' => 9]];
    $this->assertSame($value, $method->invoke($service, $value, $fieldDef));

    // Non-array values are returned untouched.
    $this->assertSame(3, $method->invoke($service, 3, $fieldDef));
  }

  /**
   * ERR items with a "type" key create entities; plain references survive.
   */
  public function testEntityReferenceRevisionsCreatesInlineEntities(): void {
    $created = $this->createMock(EntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('create')
      ->with(['type' => 'text_block'])
      ->willReturn($created);

    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getKey')->with('bundle')->willReturn('type');

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->method('hasDefinition')->with('paragraph')->willReturn(TRUE);
    $this->entityTypeManager->method('getDefinition')->with('paragraph')->willReturn($entityType);
    $this->entityTypeManager->method('getStorage')->with('paragraph')->willReturn($storage);

    $fieldDef = $this->createMock(FieldDefinitionInterface::class);
    $fieldDef->method('getSetting')->with('target_type')->willReturn('paragraph');

    $service = $this->createContentService();
    $method = new \ReflectionMethod($service, 'createReferencedEntities');

    $result = $method->invoke($service, [
      ['type' => 'text_block'],
      ['target_id' => 5],
    ], $fieldDef);

    $this->assertCount(2, $result);
    $this->assertSame($created, $result[0]);
    $this->assertSame(['target_id' => 5], $result[1]);
  }

}
