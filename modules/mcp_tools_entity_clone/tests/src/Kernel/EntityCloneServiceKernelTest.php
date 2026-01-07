<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_entity_clone\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_entity_clone\Service\EntityCloneService;

/**
 * Kernel tests for EntityCloneService.
 *
 * @group mcp_tools_entity_clone
 * @requires module entity_clone
 */
final class EntityCloneServiceKernelTest extends KernelTestBase {

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
    'entity_clone',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_entity_clone',
  ];

  /**
   * The entity clone service under test.
   */
  private EntityCloneService $entityCloneService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mcp_tools', 'node']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    $this->entityCloneService = $this->container->get('mcp_tools_entity_clone.entity_clone');

    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
  }

  /**
   * Test getting cloneable types.
   */
  public function testGetCloneableTypes(): void {
    $result = $this->entityCloneService->getCloneableTypes();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('types', $result['data']);
    $this->assertIsArray($result['data']['types']);
  }

  /**
   * Test cloning unsupported entity type.
   */
  public function testCloneUnsupportedEntityType(): void {
    $result = $this->entityCloneService->cloneEntity('unsupported_type', 1);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not supported', $result['error']);
    $this->assertArrayHasKey('supported_types', $result);
  }

  /**
   * Test cloning non-existent entity.
   */
  public function testCloneNonExistentEntity(): void {
    $result = $this->entityCloneService->cloneEntity('node', 99999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test clone with references on unsupported type.
   */
  public function testCloneWithReferencesUnsupportedType(): void {
    $result = $this->entityCloneService->cloneWithReferences('unsupported_type', 1);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not supported', $result['error']);
  }

  /**
   * Test clone with references on non-existent entity.
   */
  public function testCloneWithReferencesNonExistent(): void {
    $result = $this->entityCloneService->cloneWithReferences('node', 99999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test get clone settings.
   */
  public function testGetCloneSettings(): void {
    // Create a content type first.
    $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->create(['type' => 'test_type', 'name' => 'Test Type'])
      ->save();

    $result = $this->entityCloneService->getCloneSettings('node', 'test_type');

    $this->assertTrue($result['success']);
    $this->assertSame('node', $result['data']['entity_type']);
    $this->assertSame('test_type', $result['data']['bundle']);
    $this->assertArrayHasKey('settings', $result['data']);
    $this->assertArrayHasKey('reference_fields', $result['data']);
  }

  /**
   * Test write operations require write scope.
   */
  public function testWriteOperationsRequireWriteScope(): void {
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    $result = $this->entityCloneService->cloneEntity('node', 1);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);

    $result = $this->entityCloneService->cloneWithReferences('node', 1);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);
  }

}
