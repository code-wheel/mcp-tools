<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_metatag\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_metatag\Service\MetatagService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for MetatagService.
 *
 * @coversDefaultClass \Drupal\mcp_tools_metatag\Service\MetatagService
 * @group mcp_tools_metatag
 */
class MetatagServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
  }

  /**
   * Creates a MetatagService instance.
   */
  protected function createService(): MetatagService {
    return new MetatagService(
      $this->entityTypeManager,
      $this->accessManager,
      $this->auditLogger
    );
  }

  /**
   * @covers ::listDefaults
   */
  public function testListDefaultsEmpty(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn([]);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $service = $this->createService();
    $result = $service->listDefaults();

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['defaults']);
  }

  /**
   * @covers ::getDefault
   */
  public function testGetDefaultNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $service = $this->createService();
    $result = $service->getDefault('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::updateDefault
   */
  public function testUpdateDefaultAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->updateDefault('global', []);

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * @covers ::updateDefault
   */
  public function testUpdateDefaultNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $service = $this->createService();
    $result = $service->updateDefault('nonexistent', []);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::getEntityMetatags
   */
  public function testGetEntityMetatagsEntityNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $service = $this->createService();
    $result = $service->getEntityMetatags('node', 999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::setEntityMetatags
   */
  public function testSetEntityMetatagsAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->setEntityMetatags('node', 1, []);

    $this->assertFalse($result['success']);
  }

}
