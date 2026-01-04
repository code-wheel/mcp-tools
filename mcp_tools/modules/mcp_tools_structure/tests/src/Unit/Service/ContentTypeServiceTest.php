<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_structure\Service\ContentTypeService;
use Drupal\node\NodeTypeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for ContentTypeService.
 *
 * @coversDefaultClass \Drupal\mcp_tools_structure\Service\ContentTypeService
 * @group mcp_tools_structure
 */
class ContentTypeServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected EntityTypeBundleInfoInterface $bundleInfo;
  protected ConfigFactoryInterface $configFactory;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
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
    $this->nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $this->nodeStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['node_type', $this->nodeTypeStorage],
        ['node', $this->nodeStorage],
      ]);
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
      $this->auditLogger
    );
  }

  /**
   * Creates a mock node type.
   */
  protected function createMockNodeType(string $id, string $label, string $description = ''): NodeTypeInterface {
    $type = $this->createMock(NodeTypeInterface::class);
    $type->method('id')->willReturn($id);
    $type->method('label')->willReturn($label);
    $type->method('getDescription')->willReturn($description);
    return $type;
  }

  /**
   * @covers ::listContentTypes
   */
  public function testListContentTypesEmpty(): void {
    $this->nodeTypeStorage->method('loadMultiple')->willReturn([]);

    $service = $this->createService();
    $result = $service->listContentTypes();

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['types']);
  }

  /**
   * @covers ::listContentTypes
   */
  public function testListContentTypesWithTypes(): void {
    $type1 = $this->createMockNodeType('article', 'Article', 'A news article');
    $type2 = $this->createMockNodeType('page', 'Basic Page', 'A basic page');

    $this->nodeTypeStorage->method('loadMultiple')
      ->willReturn(['article' => $type1, 'page' => $type2]);

    $service = $this->createService();
    $result = $service->listContentTypes();

    $this->assertTrue($result['success']);
    $this->assertCount(2, $result['data']['types']);
  }

  /**
   * @covers ::getContentType
   */
  public function testGetContentTypeNotFound(): void {
    $this->nodeTypeStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getContentType('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::getContentType
   */
  public function testGetContentTypeSuccess(): void {
    $type = $this->createMockNodeType('article', 'Article', 'A news article');
    $this->nodeTypeStorage->method('load')->willReturn($type);

    $service = $this->createService();
    $result = $service->getContentType('article');

    $this->assertTrue($result['success']);
    $this->assertEquals('article', $result['data']['id']);
    $this->assertEquals('Article', $result['data']['label']);
  }

  /**
   * @covers ::createContentType
   */
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

  /**
   * @covers ::createContentType
   */
  public function testCreateContentTypeInvalidMachineName(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();
    $result = $service->createContentType('Test-Type', 'Test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid machine name', $result['error']);
  }

  /**
   * @covers ::createContentType
   */
  public function testCreateContentTypeAlreadyExists(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $existing = $this->createMockNodeType('test', 'Test');
    $this->nodeTypeStorage->method('load')->willReturn($existing);

    $service = $this->createService();
    $result = $service->createContentType('test', 'Test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  /**
   * @covers ::deleteContentType
   */
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

  /**
   * @covers ::deleteContentType
   */
  public function testDeleteContentTypeNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->nodeTypeStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->deleteContentType('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::deleteContentType
   */
  public function testDeleteContentTypeInUse(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $type = $this->createMockNodeType('test', 'Test');
    $this->nodeTypeStorage->method('load')->willReturn($type);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn(5);

    $this->nodeStorage->method('getQuery')->willReturn($query);

    $service = $this->createService();
    $result = $service->deleteContentType('test');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('in use', $result['error']);
  }

}
