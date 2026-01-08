<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_jsonapi\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_jsonapi\Service\JsonApiService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(JsonApiService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_jsonapi')]
final class JsonApiServiceTest extends UnitTestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private ResourceTypeRepositoryInterface $resourceTypeRepository;
  private EntityRepositoryInterface $entityRepository;
  private ConfigFactoryInterface $configFactory;
  private AccessManager $accessManager;
  private AuditLogger $auditLogger;
  private AccountProxyInterface $currentUser;
  private JsonApiService $service;

  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->resourceTypeRepository = $this->createMock(ResourceTypeRepositoryInterface::class);
    $this->entityRepository = $this->createMock(EntityRepositoryInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);

    // Default config mock.
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(function ($key) {
      return match ($key) {
        'allowed_entity_types' => [],
        'blocked_entity_types' => ['user', 'shortcut'],
        'allow_write_operations' => TRUE,
        'max_items_per_page' => 50,
        'include_relationships' => FALSE,
        default => NULL,
      };
    });
    $this->configFactory->method('get')->with('mcp_tools_jsonapi.settings')->willReturn($config);

    $this->service = new JsonApiService(
      $this->entityTypeManager,
      $this->resourceTypeRepository,
      $this->entityRepository,
      $this->configFactory,
      $this->accessManager,
      $this->auditLogger,
      $this->currentUser,
    );
  }

  public function testGetEntityReturnsErrorForBlockedType(): void {
    $result = $this->service->getEntity('user', 'some-uuid');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not accessible', $result['error']);
  }

  public function testGetEntityReturnsErrorForHardcodedBlockedType(): void {
    // 'shortcut' is in ALWAYS_BLOCKED constant.
    $result = $this->service->getEntity('shortcut', 'some-uuid');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not accessible', $result['error']);
  }

  public function testGetEntityReturnsErrorWhenNotFound(): void {
    $this->entityRepository->method('loadEntityByUuid')
      ->with('node', 'missing-uuid')
      ->willReturn(NULL);

    $result = $this->service->getEntity('node', 'missing-uuid');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testListEntitiesReturnsErrorForBlockedType(): void {
    $result = $this->service->listEntities('user');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not accessible', $result['error']);
  }

  public function testCreateEntityReturnsErrorWhenWriteDisabled(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $result = $this->service->createEntity('node', 'article', ['title' => 'Test']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('denied', $result['error']);
  }

  public function testCreateEntityReturnsErrorForBlockedType(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $result = $this->service->createEntity('user', 'user', ['name' => 'test']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not accessible', $result['error']);
  }

  public function testUpdateEntityReturnsErrorWhenWriteDisabled(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $result = $this->service->updateEntity('node', 'some-uuid', ['title' => 'New Title']);

    $this->assertFalse($result['success']);
  }

  public function testDeleteEntityReturnsErrorWhenWriteDisabled(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $result = $this->service->deleteEntity('node', 'some-uuid');

    $this->assertFalse($result['success']);
  }

  public function testDeleteEntityReturnsErrorForBlockedType(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $result = $this->service->deleteEntity('user', 'some-uuid');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not accessible', $result['error']);
  }

  public function testDiscoverTypesReturnsArray(): void {
    $this->resourceTypeRepository->method('all')->willReturn([]);

    $result = $this->service->discoverTypes();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('data', $result);
    $this->assertArrayHasKey('types', $result['data']);
    $this->assertIsArray($result['data']['types']);
  }

}
