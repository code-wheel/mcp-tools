<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_structure\Service\RoleService;
use Drupal\Tests\UnitTestCase;
use Drupal\user\PermissionHandlerInterface;
use Drupal\user\RoleInterface;

/**
 * Tests for RoleService.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_structure\Service\RoleService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_structure')]
class RoleServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected PermissionHandlerInterface $permissionHandler;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $roleStorage;
  protected EntityStorageInterface $userStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->permissionHandler = $this->createMock(PermissionHandlerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);

    $this->roleStorage = $this->createMock(EntityStorageInterface::class);
    $this->userStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['user_role', $this->roleStorage],
        ['user', $this->userStorage],
      ]);
  }

  /**
   * Creates a RoleService instance.
   */
  protected function createRoleService(): RoleService {
    return new RoleService(
      $this->entityTypeManager,
      $this->permissionHandler,
      $this->accessManager,
      $this->auditLogger
    );
  }

  public function testCreateRoleAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createRoleService();
    $result = $service->createRole('editor', 'Editor');

    $this->assertFalse($result['success']);
  }

  #[\PHPUnit\Framework\Attributes\DataProvider('invalidMachineNameProvider')]
  public function testCreateRoleInvalidMachineName(string $invalidName): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createRoleService();
    $result = $service->createRole($invalidName, 'Invalid Role');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid machine name', $result['error']);
  }

  /**
   * Data provider for invalid machine names.
   */
  public static function invalidMachineNameProvider(): array {
    return [
      'starts with number' => ['1role'],
      'uppercase letters' => ['MyRole'],
      'has spaces' => ['my role'],
      'has dashes' => ['my-role'],
      'special chars' => ['role@test'],
    ];
  }

  public function testCreateRoleMachineNameTooLong(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createRoleService();
    $result = $service->createRole(str_repeat('a', 33), 'Long Role');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('32 characters', $result['error']);
  }

  #[\PHPUnit\Framework\Attributes\DataProvider('reservedRoleIdProvider')]
  public function testCreateRoleReservedIds(string $reservedId): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createRoleService();
    $result = $service->createRole($reservedId, 'Reserved Role');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('reserved', $result['error']);
  }

  /**
   * Data provider for reserved role IDs.
   */
  public static function reservedRoleIdProvider(): array {
    return [
      'anonymous' => ['anonymous'],
      'authenticated' => ['authenticated'],
      'administrator' => ['administrator'],
    ];
  }

  public function testCreateRoleAlreadyExists(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $existingRole = $this->createMock(RoleInterface::class);
    $this->roleStorage->method('load')->with('editor')->willReturn($existingRole);

    $service = $this->createRoleService();
    $result = $service->createRole('editor', 'Editor');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  public function testCreateRoleWithDangerousPermissions(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->roleStorage->method('load')->with('power_user')->willReturn(NULL);

    // Mock permission handler to return all permissions as valid.
    $this->permissionHandler->method('getPermissions')->willReturn([
      'administer users' => ['title' => 'Administer users'],
      'access content' => ['title' => 'Access content'],
    ]);

    $service = $this->createRoleService();
    $result = $service->createRole('power_user', 'Power User', ['administer users']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('dangerous permissions', $result['error']);
    $this->assertStringContainsString('administer users', $result['error']);
  }

  public function testCreateRoleWithInvalidPermissions(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->roleStorage->method('load')->with('custom_role')->willReturn(NULL);

    // Mock permission handler.
    $this->permissionHandler->method('getPermissions')->willReturn([
      'access content' => ['title' => 'Access content'],
    ]);

    $service = $this->createRoleService();
    $result = $service->createRole('custom_role', 'Custom Role', ['fake_permission']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid permissions', $result['error']);
    $this->assertStringContainsString('fake_permission', $result['error']);
  }

  public function testDeleteRoleAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createRoleService();
    $result = $service->deleteRole('editor');

    $this->assertFalse($result['success']);
  }

  #[\PHPUnit\Framework\Attributes\DataProvider('coreRoleIdProvider')]
  public function testDeleteRolePreventsDeleteOfCoreRoles(string $coreRole): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createRoleService();
    $result = $service->deleteRole($coreRole);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Cannot delete core role', $result['error']);
  }

  /**
   * Data provider for core role IDs.
   */
  public static function coreRoleIdProvider(): array {
    return [
      'anonymous' => ['anonymous'],
      'authenticated' => ['authenticated'],
    ];
  }

  public function testDeleteRoleNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->roleStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $service = $this->createRoleService();
    $result = $service->deleteRole('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testGrantPermissionsAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createRoleService();
    $result = $service->grantPermissions('editor', ['access content']);

    $this->assertFalse($result['success']);
  }

  public function testGrantPermissionsRoleNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->roleStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $service = $this->createRoleService();
    $result = $service->grantPermissions('nonexistent', ['access content']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testGrantPermissionsBlocksDangerousPermissions(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $role = $this->createMock(RoleInterface::class);
    $this->roleStorage->method('load')->with('editor')->willReturn($role);

    $this->permissionHandler->method('getPermissions')->willReturn([
      'bypass node access' => ['title' => 'Bypass node access'],
      'access content' => ['title' => 'Access content'],
    ]);

    $service = $this->createRoleService();
    $result = $service->grantPermissions('editor', ['bypass node access']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('dangerous permissions', $result['error']);
    $this->assertStringContainsString('bypass node access', $result['error']);
  }

  #[\PHPUnit\Framework\Attributes\DataProvider('dangerousPermissionsProvider')]
  public function testGrantPermissionsBlocksAllDangerousPermissions(string $dangerousPermission): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $role = $this->createMock(RoleInterface::class);
    $this->roleStorage->method('load')->with('editor')->willReturn($role);

    $this->permissionHandler->method('getPermissions')->willReturn([
      $dangerousPermission => ['title' => $dangerousPermission],
    ]);

    $service = $this->createRoleService();
    $result = $service->grantPermissions('editor', [$dangerousPermission]);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('dangerous permissions', $result['error']);
  }

  /**
   * Data provider for all dangerous permissions.
   */
  public static function dangerousPermissionsProvider(): array {
    return [
      'administer permissions' => ['administer permissions'],
      'administer users' => ['administer users'],
      'administer site configuration' => ['administer site configuration'],
      'administer modules' => ['administer modules'],
      'administer software updates' => ['administer software updates'],
      'administer themes' => ['administer themes'],
      'bypass node access' => ['bypass node access'],
      'synchronize configuration' => ['synchronize configuration'],
      'import configuration' => ['import configuration'],
      'export configuration' => ['export configuration'],
    ];
  }

  public function testRevokePermissionsAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createRoleService();
    $result = $service->revokePermissions('editor', ['access content']);

    $this->assertFalse($result['success']);
  }

  public function testRevokePermissionsRoleNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->roleStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $service = $this->createRoleService();
    $result = $service->revokePermissions('nonexistent', ['access content']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testRevokePermissionsTracksDidntHave(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $role = $this->createMock(RoleInterface::class);
    $role->method('label')->willReturn('Editor');
    $role->method('hasPermission')
      ->willReturnCallback(function ($permission) {
        return $permission === 'access content';
      });

    $this->roleStorage->method('load')->with('editor')->willReturn($role);

    $service = $this->createRoleService();
    $result = $service->revokePermissions('editor', ['access content', 'create article']);

    $this->assertTrue($result['success']);
    $this->assertContains('access content', $result['data']['revoked']);
    $this->assertContains('create article', $result['data']['didnt_have']);
  }

}
