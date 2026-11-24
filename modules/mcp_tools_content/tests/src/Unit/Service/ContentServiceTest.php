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
   * The content tool stores each field's value as the shared FieldValue does.
   *
   * It asserted nothing (assertTrue(TRUE)), and so missed that this copy
   * had drifted from the import's.
   *
   * @dataProvider normalizeFieldValueProvider
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('normalizeFieldValueProvider')]
  public function testNormalizeFieldValueBehavior(string $fieldType, mixed $input, mixed $expected): void {
    $fieldDef = $this->createMock(FieldDefinitionInterface::class);
    $fieldDef->method('getType')->willReturn($fieldType);
    $service = $this->createContentService();

    $result = (new \ReflectionMethod($service, 'normalizeFieldValue'))->invoke($service, 'field_test', $input, ['field_test' => $fieldDef]);

    if (is_array($expected)) {
      // The keys given are kept; a text value gets a format besides.
      foreach ($expected as $key => $value) {
        $this->assertSame($value, $result[$key] ?? NULL, "$fieldType: $key");
      }
    }
    else {
      $this->assertSame($expected, $result);
    }
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
      'link path' => ['link', '/about', ['uri' => 'internal:/about']],
      'datetime string' => ['datetime', '2024-01-15', ['value' => '2024-01-15']],
      'string direct' => ['string', 'plain value', 'plain value'],
    ];
  }

  // The tests of paragraphs given inline (plain references pass through,
  // items with a "type" are made) moved with the code to
  // \Drupal\Tests\mcp_tools\Unit\Service\FieldValueTest.

}
