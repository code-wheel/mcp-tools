<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_search_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_search_api\Service\SearchApiService;

/**
 * Kernel tests for SearchApiService.
 *
 * @group mcp_tools_search_api
 * @requires module search_api
 */
final class SearchApiServiceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'search_api',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_search_api',
  ];

  /**
   * The search API service under test.
   */
  private SearchApiService $searchApiService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mcp_tools']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('search_api_index');
    $this->installEntitySchema('search_api_server');

    $this->searchApiService = $this->container->get('mcp_tools_search_api.search_api');

    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
  }

  /**
   * Test listing indexes when none exist.
   */
  public function testListIndexesEmpty(): void {
    $result = $this->searchApiService->listIndexes();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('indexes', $result['data']);
    $this->assertEmpty($result['data']['indexes']);
  }

  /**
   * Test listing servers when none exist.
   */
  public function testListServersEmpty(): void {
    $result = $this->searchApiService->listServers();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('servers', $result['data']);
    $this->assertEmpty($result['data']['servers']);
  }

  /**
   * Test getting non-existent index.
   */
  public function testGetIndexNotFound(): void {
    $result = $this->searchApiService->getIndex('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test getting non-existent server.
   */
  public function testGetServerNotFound(): void {
    $result = $this->searchApiService->getServer('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test getting status of non-existent index.
   */
  public function testGetIndexStatusNotFound(): void {
    $result = $this->searchApiService->getIndexStatus('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test reindexing non-existent index.
   */
  public function testReindexIndexNotFound(): void {
    $result = $this->searchApiService->reindexIndex('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test indexing items for non-existent index.
   */
  public function testIndexItemsNotFound(): void {
    $result = $this->searchApiService->indexItems('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test clearing non-existent index.
   */
  public function testClearIndexNotFound(): void {
    $result = $this->searchApiService->clearIndex('nonexistent');

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

    $result = $this->searchApiService->reindexIndex('test');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);

    $result = $this->searchApiService->indexItems('test');
    $this->assertFalse($result['success']);

    $result = $this->searchApiService->clearIndex('test');
    $this->assertFalse($result['success']);
  }

}
