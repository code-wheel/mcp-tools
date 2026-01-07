<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_metatag\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_metatag\Service\MetatagService;

/**
 * Kernel tests for MetatagService.
 *
 * @group mcp_tools_metatag
 * @requires module metatag
 */
final class MetatagServiceKernelTest extends KernelTestBase {

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
    'token',
    'metatag',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_metatag',
  ];

  /**
   * The metatag service under test.
   */
  private MetatagService $metatagService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mcp_tools', 'metatag']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    $this->metatagService = $this->container->get('mcp_tools_metatag.metatag');

    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
  }

  /**
   * Test getting metatag defaults.
   */
  public function testGetMetatagDefaults(): void {
    $result = $this->metatagService->getMetatagDefaults();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('defaults', $result['data']);
  }

  /**
   * Test getting metatag defaults for specific type.
   */
  public function testGetMetatagDefaultsForType(): void {
    $result = $this->metatagService->getMetatagDefaults('global');

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('defaults', $result['data']);
  }

  /**
   * Test listing metatag groups.
   */
  public function testListMetatagGroups(): void {
    $result = $this->metatagService->listMetatagGroups();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('groups', $result['data']);
    $this->assertNotEmpty($result['data']['groups']);
  }

  /**
   * Test listing available tags.
   */
  public function testListAvailableTags(): void {
    $result = $this->metatagService->listAvailableTags();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('tags', $result['data']);
    $this->assertNotEmpty($result['data']['tags']);
  }

  /**
   * Test getting entity metatags for non-existent entity.
   */
  public function testGetEntityMetatagsNotFound(): void {
    $result = $this->metatagService->getEntityMetatags('node', 99999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test setting entity metatags for non-existent entity.
   */
  public function testSetEntityMetatagsNotFound(): void {
    $result = $this->metatagService->setEntityMetatags('node', 99999, ['title' => 'Test']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test write operations require write scope.
   */
  public function testWriteOperationsRequireWriteScope(): void {
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    $result = $this->metatagService->setEntityMetatags('node', 1, ['title' => 'Test']);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);
  }

}
