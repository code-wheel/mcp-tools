<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_structure\Service\ContentTypeService;
use Drupal\node\NodeTypeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for ContentTypeService.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_structure\Service\ContentTypeService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_structure')]
class ContentTypeServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected EntityTypeBundleInfoInterface $bundleInfo;
  protected ConfigFactoryInterface $configFactory;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityFieldManagerInterface $entityFieldManager;
  protected EntityStorageInterface $nodeTypeStorage;
  protected EntityStorageInterface $nodeStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->bundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $this->nodeStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['node_type', $this->nodeTypeStorage],
        ['node', $this->nodeStorage],
      ]);

    // Default: return empty field definitions.
    $this->entityFieldManager->method('getFieldDefinitions')
      ->willReturn([]);
  }

  /**
   * Creates a ContentTypeService instance.
   */
  protected function createService(): ContentTypeService {
    return new ContentTypeService(
      $this->entityTypeManager,
      $this->bundleInfo,
      $this->configFactory,
      $this->accessManager,
      $this->auditLogger,
      $this->entityFieldManager,
    );
  }

  /**
   * Creates a mock node type.
   */
  protected function createMockNodeType(string $id, string $label, string $description = ''): NodeTypeInterface {
    $type = $this->createMock(NodeTypeInterface::class);
    $type->method('id')->willReturn($id);
    $type->method('label')->willReturn($label);
    // getDescription returns TranslatableMarkup in Drupal 10+.
    $descriptionMock = $this->createMock(TranslatableMarkup::class);
    $descriptionMock->method('__toString')->willReturn($description);
    $type->method('getDescription')->willReturn($descriptionMock);
    return $type;
  }

  /**
   * Creates a mock count query that returns the specified count.
   */
  protected function createMockCountQuery(int $count): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn($count);
    return $query;
  }

  public function testListContentTypesEmpty(): void {
    $this->nodeTypeStorage->method('loadMultiple')->willReturn([]);

    $service = $this->createService();
    $result = $service->listContentTypes();

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['types']);
  }

  public function testListContentTypesWithTypes(): void {
    $type1 = $this->createMockNodeType('article', 'Article', 'A news article');
    $type2 = $this->createMockNodeType('page', 'Basic Page', 'A basic page');

    $this->nodeTypeStorage->method('loadMultiple')
      ->willReturn(['article' => $type1, 'page' => $type2]);

    // Mock the count query for each type.
    $this->nodeStorage->method('getQuery')
      ->willReturn($this->createMockCountQuery(0));

    $service = $this->createService();
    $result = $service->listContentTypes();

    $this->assertTrue($result['success']);
    $this->assertCount(2, $result['data']['types']);
  }

  public function testGetContentTypeNotFound(): void {
    $this->nodeTypeStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getContentType('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testGetContentTypeSuccess(): void {
    $type = $this->createMockNodeType('article', 'Article', 'A news article');
    $this->nodeTypeStorage->method('load')->willReturn($type);

    $service = $this->createService();
    $result = $service->getContentType('article');

    $this->assertTrue($result['success']);
    $this->assertEquals('article', $result['data']['id']);
    $this->assertEquals('Article', $result['data']['label']);
  }

  public function testCreateContentTypeAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->createContentType('test', 'Test');

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  public function testCreateContentTypeInvalidMachineName(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();
    $result = $service->createContentType('Test-Type', 'Test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid machine name', $result['error']);
  }

  public function testCreateContentTypeAlreadyExists(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $existing = $this->createMockNodeType('test', 'Test');
    $this->nodeTypeStorage->method('load')->willReturn($existing);

    $service = $this->createService();
    $result = $service->createContentType('test', 'Test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  public function testDeleteContentTypeAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->deleteContentType('test');

    $this->assertFalse($result['success']);
  }

  public function testDeleteContentTypeNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->nodeTypeStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->deleteContentType('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testDeleteContentTypeHasContent(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $type = $this->createMockNodeType('test', 'Test');
    $this->nodeTypeStorage->method('load')->willReturn($type);

    $this->nodeStorage->method('getQuery')
      ->willReturn($this->createMockCountQuery(5));

    $service = $this->createService();
    $result = $service->deleteContentType('test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('has 5 nodes', $result['error']);
  }

}
