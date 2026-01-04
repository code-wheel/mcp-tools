<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_config\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_config\Service\ConfigManagementService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\mcp_tools_config\Service\ConfigManagementService
 * @group mcp_tools_config
 */
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
    return new ConfigManagementService(
      $this->createMock(ConfigFactoryInterface::class),
      $this->active,
      $this->sync,
      $this->state,
      $this->createMock(FileSystemInterface::class),
      $this->accessManager,
      $this->auditLogger,
    );
  }

  /**
   * @covers ::getConfigChanges
   */
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

  /**
   * @covers ::exportConfig
   */
  public function testExportConfigRequiresAdminScope(): void {
    $this->accessManager->method('canAdmin')->willReturn(FALSE);

    $service = $this->createService();
    $result = $service->exportConfig();

    $this->assertFalse($result['success']);
    $this->assertSame('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * @covers ::exportConfig
   */
  public function testExportConfigNoChangesReturnsEarly(): void {
    $this->accessManager->method('canAdmin')->willReturn(TRUE);
    $this->active->write('system.site', ['name' => 'same']);
    $this->sync->write('system.site', ['name' => 'same']);

    $service = $this->createService();
    $result = $service->exportConfig();

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['data']['exported']);
  }

  /**
   * @covers ::exportConfig
   * @covers ::trackChange
   */
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

  /**
   * @covers ::getConfigDiff
   */
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

  /**
   * @covers ::previewOperation
   */
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

  /**
   * @covers ::trackChange
   * @covers ::getMcpChanges
   */
  public function testTrackChangeDeduplicatesByConfigName(): void {
    $service = $this->createService();

    $service->trackChange('system.site', 'create');
    $service->trackChange('system.site', 'update');

    $result = $service->getMcpChanges();
    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['total']);
    $this->assertSame('update', $result['data']['changes'][0]['operation']);
  }

  /**
   * @covers ::previewOperation
   */
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

  /**
   * @covers ::previewOperation
   */
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

}
