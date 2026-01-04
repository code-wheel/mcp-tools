<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_entity_clone\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_entity_clone\Service\EntityCloneService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for EntityCloneService.
 *
 * @coversDefaultClass \Drupal\mcp_tools_entity_clone\Service\EntityCloneService
 * @group mcp_tools_entity_clone
 */
class EntityCloneServiceTest extends UnitTestCase {

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
   * Creates an EntityCloneService instance.
   */
  protected function createService(): EntityCloneService {
    return new EntityCloneService(
      $this->entityTypeManager,
      $this->accessManager,
      $this->auditLogger
    );
  }

  /**
   * @covers ::cloneEntity
   */
  public function testCloneEntityAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->cloneEntity('node', 1);

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * @covers ::cloneEntity
   */
  public function testCloneEntityNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $service = $this->createService();
    $result = $service->cloneEntity('node', 999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::getCloneableEntityTypes
   */
  public function testGetCloneableEntityTypes(): void {
    $service = $this->createService();
    $result = $service->getCloneableEntityTypes();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('entity_types', $result['data']);
  }

  /**
   * @covers ::getCloneSettings
   */
  public function testGetCloneSettingsInvalidEntityType(): void {
    $this->entityTypeManager->method('getDefinition')
      ->willThrowException(new \Exception('Invalid entity type'));

    $service = $this->createService();
    $result = $service->getCloneSettings('invalid_type');

    $this->assertFalse($result['success']);
  }

}
