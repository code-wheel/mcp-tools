<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_pathauto\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_pathauto\Service\PathautoService;

/**
 * Kernel tests for PathautoService.
 *
 * @group mcp_tools_pathauto
 * @requires module pathauto
 */
final class PathautoServiceKernelTest extends KernelTestBase {

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
    'path',
    'path_alias',
    'token',
    'ctools',
    'pathauto',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_pathauto',
  ];

  /**
   * The pathauto service under test.
   */
  private PathautoService $pathautoService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mcp_tools', 'pathauto']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('pathauto_pattern');

    $this->pathautoService = $this->container->get('mcp_tools_pathauto.pathauto');

    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
  }

  /**
   * Test listing patterns when none exist.
   */
  public function testListPatternsEmpty(): void {
    $result = $this->pathautoService->listPatterns();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('patterns', $result['data']);
  }

  /**
   * Test listing patterns for specific entity type.
   */
  public function testListPatternsForEntityType(): void {
    $result = $this->pathautoService->listPatterns('node');

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('patterns', $result['data']);
  }

  /**
   * Test getting non-existent pattern.
   */
  public function testGetPatternNotFound(): void {
    $result = $this->pathautoService->getPattern('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test creating a pattern.
   */
  public function testCreatePattern(): void {
    $result = $this->pathautoService->createPattern(
      'test_pattern',
      'Test Pattern',
      '/test/[node:title]',
      'node'
    );

    $this->assertTrue($result['success']);
    $this->assertSame('test_pattern', $result['data']['id']);
    $this->assertSame('Test Pattern', $result['data']['label']);
  }

  /**
   * Test creating duplicate pattern fails.
   */
  public function testCreateDuplicatePatternFails(): void {
    $this->pathautoService->createPattern('dup_pattern', 'Dup', '/test', 'node');

    $result = $this->pathautoService->createPattern('dup_pattern', 'Dup 2', '/test2', 'node');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  /**
   * Test updating non-existent pattern.
   */
  public function testUpdatePatternNotFound(): void {
    $result = $this->pathautoService->updatePattern('nonexistent', ['label' => 'New']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test deleting non-existent pattern.
   */
  public function testDeletePatternNotFound(): void {
    $result = $this->pathautoService->deletePattern('nonexistent');

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

    $result = $this->pathautoService->createPattern('test', 'Test', '/test', 'node');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);

    $result = $this->pathautoService->updatePattern('test', ['label' => 'New']);
    $this->assertFalse($result['success']);

    $result = $this->pathautoService->deletePattern('test');
    $this->assertFalse($result['success']);

    $result = $this->pathautoService->generateAliases('node');
    $this->assertFalse($result['success']);
  }

}
