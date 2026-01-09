<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_config\Unit\Service;

use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_config\Service\ConfigComparisonService;
use Drupal\mcp_tools_config\Service\ConfigManagementService;
use Drupal\mcp_tools_config\Service\McpChangeTracker;
use Drupal\mcp_tools_config\Service\OperationPreviewService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_config\Service\ConfigManagementService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_config')]
final class ConfigManagementServiceTest extends UnitTestCase {

  private MemoryStorage $active;
  private MemoryStorage $sync;
  private StateInterface $state;
  private array $stateStorage;
  private AccessManager $accessManager;
  private AuditLogger $auditLogger;

  protected function setUp(): void {
    parent::setUp();

    $this->active = new MemoryStorage();
    $this->sync = new MemoryStorage();

    $this->stateStorage = [];
    $this->state = $this->createMock(StateInterface::class);
    $this->state->method('get')->willReturnCallback(function (string $key, mixed $default = NULL): mixed {
      return $this->stateStorage[$key] ?? $default;
    });
    $this->state->method('set')->willReturnCallback(function (string $key, mixed $value): void {
      $this->stateStorage[$key] = $value;
    });
    $this->state->method('delete')->willReturnCallback(function (string $key): void {
      unset($this->stateStorage[$key]);
    });

    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
  }

  private function createService(): ConfigManagementService {
    // Create the specialized services.
    $configComparisonService = new ConfigComparisonService($this->active, $this->sync);
    $mcpChangeTracker = new McpChangeTracker($this->state);
    $operationPreviewService = new OperationPreviewService($this->active, $configComparisonService);

    return new ConfigManagementService(
      $this->active,
      $this->sync,
      $this->accessManager,
      $this->auditLogger,
      $configComparisonService,
      $mcpChangeTracker,
      $operationPreviewService,
    );
  }

  public function testGetConfigChangesDetectsCreateUpdateDelete(): void {
    $this->active->write('new.config', ['x' => 1]);
    $this->active->write('system.site', ['name' => 'active']);

    $this->sync->write('system.site', ['name' => 'sync']);
    $this->sync->write('obsolete.config', ['y' => 2]);

    $service = $this->createService();
    $result = $service->getConfigChanges();

    $this->assertTrue($result['success']);
    $data = $result['data'];
    $this->assertTrue($data['has_changes']);
    $this->assertSame(3, $data['total_changes']);

    $this->assertContains('new.config', $data['changes']['create']);
    $this->assertContains('system.site', $data['changes']['update']);
    $this->assertContains('obsolete.config', $data['changes']['delete']);
  }

  public function testExportConfigRequiresAdminScope(): void {
    $this->accessManager->method('canAdmin')->willReturn(FALSE);

    $service = $this->createService();
    $result = $service->exportConfig();

    $this->assertFalse($result['success']);
    $this->assertSame('INSUFFICIENT_SCOPE', $result['code']);
  }

  public function testExportConfigNoChangesReturnsEarly(): void {
    $this->accessManager->method('canAdmin')->willReturn(TRUE);
    $this->active->write('system.site', ['name' => 'same']);
    $this->sync->write('system.site', ['name' => 'same']);

    $service = $this->createService();
    $result = $service->exportConfig();

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['data']['exported']);
  }

  public function testExportConfigWritesAndDeletesAndClearsTrackedChanges(): void {
    $this->accessManager->method('canAdmin')->willReturn(TRUE);

    $this->active->write('system.site', ['name' => 'active']);
    $this->active->write('new.config', ['x' => 1]);
    $this->sync->write('system.site', ['name' => 'sync']);
    $this->sync->write('obsolete.config', ['y' => 2]);

    $service = $this->createService();

    // Seed tracked changes to ensure export clears them.
    $service->trackChange('system.site', 'update');
    $this->assertNotEmpty($this->stateStorage['mcp_tools.config_changes'] ?? []);

    $this->auditLogger->expects($this->once())->method('logSuccess');

    $result = $service->exportConfig();
    $this->assertTrue($result['success']);

    $names = $this->sync->listAll();
    sort($names);
    $this->assertSame(['new.config', 'system.site'], $names);
    $this->assertArrayNotHasKey('mcp_tools.config_changes', $this->stateStorage);
  }

  public function testGetConfigDiffReturnsExpectedStatuses(): void {
    $service = $this->createService();

    $missing = $service->getConfigDiff('does.not.exist');
    $this->assertFalse($missing['success']);

    $this->sync->write('only.in.sync', ['a' => 1]);
    $deleted = $service->getConfigDiff('only.in.sync');
    $this->assertTrue($deleted['success']);
    $this->assertSame('deleted_from_active', $deleted['data']['status']);

    $this->active->write('only.in.active', ['b' => 2]);
    $new = $service->getConfigDiff('only.in.active');
    $this->assertTrue($new['success']);
    $this->assertSame('new_in_active', $new['data']['status']);

    $this->active->write('same.config', ['c' => 3]);
    $this->sync->write('same.config', ['c' => 3]);
    $same = $service->getConfigDiff('same.config');
    $this->assertTrue($same['success']);
    $this->assertSame('unchanged', $same['data']['status']);

    $this->active->write('changed.config', ['nested' => ['x' => 2]]);
    $this->sync->write('changed.config', ['nested' => ['x' => 1]]);
    $changed = $service->getConfigDiff('changed.config');
    $this->assertTrue($changed['success']);
    $this->assertSame('modified', $changed['data']['status']);
    $this->assertNotEmpty($changed['data']['diff']);
  }

  public function testPreviewOperationAddsDryRunMetadata(): void {
    $this->active->write('node.type.article', ['type' => 'article']);

    $service = $this->createService();
    $result = $service->previewOperation('create_content_type', [
      'machine_name' => 'article',
      'name' => 'Article',
    ]);

    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['dry_run']);
    $this->assertSame('create_content_type', $result['data']['operation']);
    $this->assertSame('article', $result['data']['machine_name']);
    $this->assertTrue($result['data']['already_exists']);
  }

  public function testTrackChangeDeduplicatesByConfigName(): void {
    $service = $this->createService();

    $service->trackChange('system.site', 'create');
    $service->trackChange('system.site', 'update');

    $result = $service->getMcpChanges();
    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['total']);
    $this->assertSame('update', $result['data']['changes'][0]['operation']);
  }

  public function testPreviewOperationSupportsRoleOperations(): void {
    $this->active->write('user.role.editor', [
      'id' => 'editor',
      'label' => 'Editor',
      'permissions' => ['access content'],
    ]);

    $service = $this->createService();

    $create = $service->previewOperation('create_role', [
      'id' => 'editor',
      'label' => 'Editor',
    ]);
    $this->assertTrue($create['success']);
    $this->assertTrue($create['data']['dry_run']);
    $this->assertSame('editor', $create['data']['role_id']);
    $this->assertTrue($create['data']['already_exists']);

    $grant = $service->previewOperation('grant_permissions', [
      'role' => 'editor',
      'permissions' => ['access content', 'administer nodes'],
    ]);
    $this->assertTrue($grant['success']);
    $this->assertContains('administer nodes', $grant['data']['will_add']);
    $this->assertContains('access content', $grant['data']['already_present']);

    $revoke = $service->previewOperation('revoke_permissions', [
      'role' => 'editor',
      'permissions' => ['access content'],
    ]);
    $this->assertTrue($revoke['success']);
    $this->assertContains('access content', $revoke['data']['will_remove']);

    $delete = $service->previewOperation('delete_role', ['id' => 'editor']);
    $this->assertTrue($delete['success']);
    $this->assertSame(['user.role.editor'], $delete['data']['configs_deleted']);
  }

  public function testPreviewOperationSupportsDeleteContentTypeAndField(): void {
    $this->active->write('node.type.article', ['type' => 'article']);
    $this->active->write('field.storage.node.field_foo', ['type' => 'string']);
    $this->active->write('field.field.node.article.field_foo', ['field_name' => 'field_foo']);

    $service = $this->createService();

    $deleteType = $service->previewOperation('delete_content_type', ['id' => 'article']);
    $this->assertTrue($deleteType['success']);
    $this->assertTrue($deleteType['data']['exists']);
    $this->assertContains('node.type.article', $deleteType['data']['configs_deleted']);

    $deleteField = $service->previewOperation('delete_field', [
      'bundle' => 'article',
      'field_name' => 'foo',
    ]);
    $this->assertTrue($deleteField['success']);
    $this->assertTrue($deleteField['data']['field_exists']);
    $this->assertTrue($deleteField['data']['storage_exists']);
    $this->assertSame('field_foo', $deleteField['data']['field_name']);
    $this->assertSame(['field.field.node.article.field_foo'], $deleteField['data']['configs_deleted']);
  }

  public function testPreviewOperationReturnsErrorForUnknownOperation(): void {
    $service = $this->createService();

    $result = $service->previewOperation('nope', []);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Unknown operation', $result['error']);
  }

  public function testPreviewExportAndImportConfigSummarizeChanges(): void {
    $this->active->write('system.site', ['name' => 'active']);
    $this->active->write('only.active', ['x' => 1]);
    $this->sync->write('system.site', ['name' => 'sync']);
    $this->sync->write('only.sync', ['y' => 2]);

    $service = $this->createService();

    $export = $service->previewOperation('export_config');
    $this->assertTrue($export['success']);
    $this->assertTrue($export['data']['dry_run']);
    $this->assertSame(1, $export['data']['will_create']);
    $this->assertSame(1, $export['data']['will_update']);
    $this->assertSame(1, $export['data']['will_delete']);

    $import = $service->previewOperation('import_config');
    $this->assertTrue($import['success']);
    $this->assertTrue($import['data']['dry_run']);
    $this->assertSame(1, $import['data']['will_create']);
    $this->assertSame(1, $import['data']['will_update']);
    $this->assertSame(1, $import['data']['will_delete']);
  }

  public function testPreviewDeleteConfigReportsDependents(): void {
    $this->active->write('foo.bar', ['a' => 1]);
    $this->active->write('dependent.config', [
      'dependencies' => [
        'config' => ['foo.bar'],
      ],
    ]);

    $service = $this->createService();
    $result = $service->previewOperation('delete_config', ['config_name' => 'foo.bar']);

    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['exists']);
    $this->assertSame(['dependent.config'], $result['data']['dependents']);
    $this->assertStringContainsString('WARNING', $result['data']['description']);
  }

  public function testPreviewAddFieldNormalizesFieldNameAndOmitsExistingStorage(): void {
    $this->active->write('field.storage.node.field_foo', ['type' => 'string']);

    $service = $this->createService();
    $result = $service->previewOperation('add_field', [
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_name' => 'foo',
      'field_type' => 'string',
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('field_foo', $result['data']['field_name']);
    $this->assertTrue($result['data']['storage_exists']);
    $this->assertFalse($result['data']['field_exists']);
    $this->assertSame(['field.field.node.article.field_foo'], array_values($result['data']['configs_created']));
  }

  public function testPreviewCreateVocabularyAndViewDetectExistingConfigs(): void {
    $this->active->write('taxonomy.vocabulary.tags', ['vid' => 'tags']);
    $this->active->write('views.view.frontpage', ['id' => 'frontpage']);

    $service = $this->createService();

    $vocab = $service->previewOperation('create_vocabulary', [
      'machine_name' => 'tags',
      'name' => 'Tags',
    ]);
    $this->assertTrue($vocab['success']);
    $this->assertTrue($vocab['data']['already_exists']);

    $view = $service->previewOperation('create_view', [
      'id' => 'frontpage',
      'label' => 'Frontpage',
    ]);
    $this->assertTrue($view['success']);
    $this->assertTrue($view['data']['already_exists']);
  }

  public function testFacadeDelegatesToCorrectServices(): void {
    $configComparisonService = $this->createMock(ConfigComparisonService::class);
    $configComparisonService->expects($this->once())->method('getConfigChanges')
      ->willReturn(['success' => TRUE, 'data' => []]);
    $configComparisonService->expects($this->once())->method('getConfigDiff')
      ->with('test.config')
      ->willReturn(['success' => TRUE, 'data' => []]);

    $mcpChangeTracker = $this->createMock(McpChangeTracker::class);
    $mcpChangeTracker->expects($this->once())->method('getMcpChanges')
      ->willReturn(['success' => TRUE, 'data' => []]);
    $mcpChangeTracker->expects($this->once())->method('trackChange')
      ->with('test.config', 'update');

    $operationPreviewService = $this->createMock(OperationPreviewService::class);
    $operationPreviewService->expects($this->once())->method('previewOperation')
      ->with('create_role', ['id' => 'test'])
      ->willReturn(['success' => TRUE, 'data' => []]);

    $service = new ConfigManagementService(
      $this->active,
      $this->sync,
      $this->accessManager,
      $this->auditLogger,
      $configComparisonService,
      $mcpChangeTracker,
      $operationPreviewService,
    );

    $service->getConfigChanges();
    $service->getConfigDiff('test.config');
    $service->getMcpChanges();
    $service->trackChange('test.config', 'update');
    $service->previewOperation('create_role', ['id' => 'test']);
  }

}
