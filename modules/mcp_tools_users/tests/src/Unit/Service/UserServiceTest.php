<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_users\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_users\Service\UserService;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Drupal\user\RoleInterface;

/**
 * Tests for UserService.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_users\Service\UserService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_users')]
class UserServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected PasswordGeneratorInterface $passwordGenerator;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $userStorage;
  protected EntityStorageInterface $roleStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
    $this->passwordGenerator->method('generate')->willReturn('generated_password_123');

    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);

    $this->userStorage = $this->createMock(EntityStorageInterface::class);
    $this->roleStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['user', $this->userStorage],
        ['user_role', $this->roleStorage],
      ]);
  }

  /**
   * Creates a UserService instance.
   */
  protected function createUserService(): UserService {
    return new UserService(
      $this->entityTypeManager,
      $this->passwordGenerator,
      $this->accessManager,
      $this->auditLogger
    );
  }

  public function testCreateUserAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createUserService();
    $result = $service->createUser('testuser', 'test@example.com');

    $this->assertFalse($result['success']);
  }

  public function testCreateUserDuplicateUsername(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $existingUser = $this->createMock(UserInterface::class);
    $this->userStorage->method('loadByProperties')
      ->willReturnMap([
        [['name' => 'existinguser'], [$existingUser]],
      ]);

    $service = $this->createUserService();
    $result = $service->createUser('existinguser', 'new@example.com');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  public function testCreateUserDuplicateEmail(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $existingUser = $this->createMock(UserInterface::class);
    $this->userStorage->method('loadByProperties')
      ->willReturnCallback(function ($properties) use ($existingUser) {
        if (isset($properties['name'])) {
          return [];
        }
        if (isset($properties['mail']) && $properties['mail'] === 'existing@example.com') {
          return [$existingUser];
        }
        return [];
      });

    $service = $this->createUserService();
    $result = $service->createUser('newuser', 'existing@example.com');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already in use', $result['error']);
  }

  public function testUpdateUserProtectsUid1(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createUserService();
    $result = $service->updateUser(1, ['email' => 'new@example.com']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('super admin', $result['error']);
    $this->assertStringContainsString('uid 1', $result['error']);
  }

  public function testUpdateUserNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->userStorage->method('load')->with(999)->willReturn(NULL);

    $service = $this->createUserService();
    $result = $service->updateUser(999, ['email' => 'new@example.com']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testUpdateUserAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createUserService();
    $result = $service->updateUser(5, ['email' => 'new@example.com']);

    $this->assertFalse($result['success']);
  }

  public function testBlockUserProtectsUid1(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createUserService();
    $result = $service->blockUser(1);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('super admin', $result['error']);
    $this->assertStringContainsString('uid 1', $result['error']);
  }

  public function testBlockUserNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->userStorage->method('load')->with(999)->willReturn(NULL);

    $service = $this->createUserService();
    $result = $service->blockUser(999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testBlockUserAlreadyBlocked(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $user = $this->createMock(UserInterface::class);
    $user->method('isActive')->willReturn(FALSE);
    $user->method('getAccountName')->willReturn('testuser');

    $this->userStorage->method('load')->with(5)->willReturn($user);

    $service = $this->createUserService();
    $result = $service->blockUser(5);

    $this->assertTrue($result['success']);
    $this->assertFalse($result['data']['changed']);
    $this->assertStringContainsString('already blocked', $result['data']['message']);
  }

  public function testActivateUserNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->userStorage->method('load')->with(999)->willReturn(NULL);

    $service = $this->createUserService();
    $result = $service->activateUser(999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testActivateUserAlreadyActive(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $user = $this->createMock(UserInterface::class);
    $user->method('isActive')->willReturn(TRUE);
    $user->method('getAccountName')->willReturn('testuser');

    $this->userStorage->method('load')->with(5)->willReturn($user);

    $service = $this->createUserService();
    $result = $service->activateUser(5);

    $this->assertTrue($result['success']);
    $this->assertFalse($result['data']['changed']);
    $this->assertStringContainsString('already active', $result['data']['message']);
  }

  public function testAssignRolesAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createUserService();
    $result = $service->assignRoles(5, ['editor']);

    $this->assertFalse($result['success']);
  }

  public function testAssignRolesNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->userStorage->method('load')->with(999)->willReturn(NULL);

    $service = $this->createUserService();
    $result = $service->assignRoles(999, ['editor']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testAssignRolesFiltersAdministrator(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $user = $this->createMock(UserInterface::class);
    $user->method('getRoles')->willReturn(['authenticated', 'editor']);
    $user->method('getAccountName')->willReturn('testuser');
    $user->method('id')->willReturn(5);

    // User::addRole should never be called for 'administrator'.
    $user->expects($this->never())
      ->method('addRole')
      ->with('administrator');

    $this->userStorage->method('load')->with(5)->willReturn($user);

    $editorRole = $this->createMock(RoleInterface::class);
    $this->roleStorage->method('load')
      ->willReturnCallback(function ($roleId) use ($editorRole) {
        if ($roleId === 'editor') {
          return $editorRole;
        }
        return NULL;
      });

    $service = $this->createUserService();
    $result = $service->assignRoles(5, ['editor', 'administrator']);

    $this->assertTrue($result['success']);
    $this->assertContains('administrator', $result['data']['blocked_roles']);
    $this->assertStringContainsString('cannot be assigned via MCP', $result['data']['blocked_roles_note']);
  }

  /**
   * Test that filterRoles correctly removes administrator.
   *
   */
  public function testFilterRolesRemovesAdministrator(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $user = $this->createMock(UserInterface::class);
    $user->method('getRoles')->willReturn([]);
    $user->method('getAccountName')->willReturn('testuser');
    $user->method('id')->willReturn(5);
    $user->method('addRole')->willReturnSelf();

    $this->userStorage->method('load')->with(5)->willReturn($user);

    $contentEditorRole = $this->createMock(RoleInterface::class);
    $this->roleStorage->method('load')
      ->willReturnCallback(function ($roleId) use ($contentEditorRole) {
        if ($roleId === 'content_editor') {
          return $contentEditorRole;
        }
        return NULL;
      });

    $service = $this->createUserService();
    $result = $service->assignRoles(5, ['content_editor', 'administrator']);

    // Verify administrator is in blocked_roles.
    $this->assertContains('administrator', $result['data']['blocked_roles']);
  }

}
