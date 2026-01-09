<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_config\Unit\Service;

use Drupal\Core\Config\StorageInterface;
use Drupal\mcp_tools_config\Service\ConfigComparisonService;
use Drupal\mcp_tools_config\Service\OperationPreviewService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for OperationPreviewService.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_config\Service\OperationPreviewService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_config')]
final class OperationPreviewServiceTest extends UnitTestCase {

  private StorageInterface $activeStorage;
  private ConfigComparisonService $configComparisonService;
  private OperationPreviewService $service;

  protected function setUp(): void {
    parent::setUp();
    $this->activeStorage = $this->createMock(StorageInterface::class);
    $this->configComparisonService = $this->createMock(ConfigComparisonService::class);

    $this->service = new OperationPreviewService(
      $this->activeStorage,
      $this->configComparisonService,
    );
  }

  public function testPreviewOperationUnknownOperation(): void {
    $result = $this->service->previewOperation('unknown_operation');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString("Unknown operation 'unknown_operation'", $result['error']);
  }

  public function testPreviewExportConfig(): void {
    $this->configComparisonService->method('getConfigChanges')->willReturn([
      'success' => TRUE,
      'data' => [
        'has_changes' => TRUE,
        'total_changes' => 5,
        'summary' => ['new_in_active' => 2, 'modified' => 3],
        'changes' => [
          'create' => ['new.config'],
          'update' => ['system.site', 'system.theme'],
          'delete' => ['old.config'],
        ],
      ],
    ]);

    $result = $this->service->previewOperation('export_config');

    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['dry_run']);
    $this->assertSame('export_config', $result['data']['operation']);
  }

  public function testPreviewImportConfig(): void {
    $this->configComparisonService->method('previewImportConfig')->willReturn([
      'success' => TRUE,
      'data' => [
        'action' => 'Import configuration from sync directory to active',
        'will_create' => 0,
        'will_update' => 0,
        'will_delete' => 0,
        'affected_configs' => [],
        'description' => 'No changes to import.',
      ],
    ]);

    $result = $this->service->previewOperation('import_config');

    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['dry_run']);
  }

  public function testPreviewDeleteConfigMissingName(): void {
    $result = $this->service->previewOperation('delete_config', []);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('required', $result['error']);
  }

  public function testPreviewDeleteConfigValidConfig(): void {
    $this->activeStorage->method('read')
      ->with('system.site')
      ->willReturn(['name' => 'Test Site']);

    $result = $this->service->previewOperation('delete_config', [
      'config_name' => 'system.site',
    ]);

    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['dry_run']);
    $this->assertSame('system.site', $result['data']['config_name']);
  }

  public function testPreviewCreateRole(): void {
    $result = $this->service->previewOperation('create_role', [
      'role_id' => 'editor',
      'label' => 'Editor',
    ]);

    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['dry_run']);
    $this->assertArrayHasKey('role_id', $result['data']);
  }

  public function testPreviewDeleteRole(): void {
    $result = $this->service->previewOperation('delete_role', [
      'role_id' => 'editor',
    ]);

    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['dry_run']);
  }

  public function testPreviewGrantPermissions(): void {
    $result = $this->service->previewOperation('grant_permissions', [
      'role_id' => 'editor',
      'permissions' => ['access content', 'create article content'],
    ]);

    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['dry_run']);
  }

  public function testPreviewRevokePermissions(): void {
    $result = $this->service->previewOperation('revoke_permissions', [
      'role_id' => 'editor',
      'permissions' => ['administer site configuration'],
    ]);

    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['dry_run']);
  }

  public function testPreviewCreateContentType(): void {
    $result = $this->service->previewOperation('create_content_type', [
      'machine_name' => 'blog',
      'name' => 'Blog Post',
    ]);

    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['dry_run']);
  }

  public function testPreviewDeleteContentType(): void {
    $result = $this->service->previewOperation('delete_content_type', [
      'machine_name' => 'blog',
    ]);

    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['dry_run']);
  }

  public function testPreviewAddField(): void {
    $result = $this->service->previewOperation('add_field', [
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_name' => 'field_subtitle',
      'field_type' => 'string',
    ]);

    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['dry_run']);
  }

  public function testPreviewDeleteField(): void {
    $result = $this->service->previewOperation('delete_field', [
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_name' => 'field_subtitle',
    ]);

    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['dry_run']);
  }

}
