<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\mcp_tools\Service\UserAnalysisService;
use Drupal\Tests\UnitTestCase;
use Drupal\user\PermissionHandlerInterface;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\UserAnalysisService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class UserAnalysisServiceTest extends UnitTestCase {

  public function testGetPermissionsGroupsByProvider(): void {
    $permissionHandler = $this->createMock(PermissionHandlerInterface::class);
    $permissionHandler->method('getPermissions')->willReturn([
      'foo permission' => ['provider' => 'foo', 'title' => 'Foo'],
      'bar permission' => ['provider' => 'bar', 'title' => 'Bar', 'restrict access' => TRUE],
      'unknown provider' => ['title' => 'Unknown'],
    ]);

    $service = new UserAnalysisService(
      $this->createMock(EntityTypeManagerInterface::class),
      $permissionHandler,
    );

    $result = $service->getPermissions();
    $this->assertSame(3, $result['total_permissions']);
    $this->assertSame(3, $result['providers']);
    $this->assertArrayHasKey('bar', $result['by_provider']);
    $this->assertTrue($result['by_provider']['bar'][0]['restrict_access']);
  }

  public function testAnalyzePermissionIncludesAdminRoles(): void {
    $admin = $this->createMock(RoleInterface::class);
    $admin->method('id')->willReturn('administrator');
    $admin->method('label')->willReturn('Administrator');
    $admin->method('isAdmin')->willReturn(TRUE);
    $admin->method('hasPermission')->willReturn(FALSE);

    $editor = $this->createMock(RoleInterface::class);
    $editor->method('id')->willReturn('editor');
    $editor->method('label')->willReturn('Editor');
    $editor->method('isAdmin')->willReturn(FALSE);
    $editor->method('hasPermission')->willReturnCallback(static fn(string $perm): bool => $perm === 'access content');

    $roleStorage = $this->createMock(EntityStorageInterface::class);
    $roleStorage->method('loadMultiple')->willReturn([$admin, $editor]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('user_role')->willReturn($roleStorage);

    $service = new UserAnalysisService(
      $entityTypeManager,
      $this->createMock(PermissionHandlerInterface::class),
    );

    $result = $service->analyzePermission('access content');
    $this->assertSame(2, $result['roles_with_permission']);
    $ids = array_column($result['roles'], 'id');
    $this->assertContains('administrator', $ids);
    $this->assertContains('editor', $ids);
  }

  public function testGetUsersReturnsCountsAndUserList(): void {
    $totalQuery = $this->createMock(QueryInterface::class);
    $totalQuery->method('accessCheck')->willReturnSelf();
    $totalQuery->method('condition')->willReturnSelf();
    $totalQuery->method('count')->willReturnSelf();
    $totalQuery->method('execute')->willReturn(10);

    $activeQuery = $this->createMock(QueryInterface::class);
    $activeQuery->method('accessCheck')->willReturnSelf();
    $activeQuery->method('condition')->willReturnSelf();
    $activeQuery->method('count')->willReturnSelf();
    $activeQuery->method('execute')->willReturn(7);

    $listQuery = $this->createMock(QueryInterface::class);
    $listQuery->method('accessCheck')->willReturnSelf();
    $listQuery->method('condition')->willReturnSelf();
    $listQuery->method('sort')->willReturnSelf();
    $listQuery->method('range')->willReturnSelf();
    $listQuery->method('execute')->willReturn([2, 3]);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('getQuery')->willReturnOnConsecutiveCalls($totalQuery, $activeQuery, $listQuery);

    $user1 = $this->createMock(UserInterface::class);
    $user1->method('id')->willReturn(2);
    $user1->method('getAccountName')->willReturn('alice');
    $user1->method('getEmail')->willReturn('alice@example.com');
    $user1->method('isActive')->willReturn(TRUE);
    $user1->method('getRoles')->willReturn(['editor']);
    $user1->method('getCreatedTime')->willReturn(1700000000);
    $user1->method('getLastAccessedTime')->willReturn(0);

    $user2 = $this->createMock(UserInterface::class);
    $user2->method('id')->willReturn(3);
    $user2->method('getAccountName')->willReturn('bob');
    $user2->method('getEmail')->willReturn('bob@example.com');
    $user2->method('isActive')->willReturn(FALSE);
    $user2->method('getRoles')->willReturn(['author']);
    $user2->method('getCreatedTime')->willReturn(1700000001);
    $user2->method('getLastAccessedTime')->willReturn(1700000100);

    $userStorage->method('loadMultiple')->with([2, 3])->willReturn([$user1, $user2]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('user')->willReturn($userStorage);

    $service = new UserAnalysisService(
      $entityTypeManager,
      $this->createMock(PermissionHandlerInterface::class),
    );

    $result = $service->getUsers(50, NULL);
    $this->assertSame(10, $result['total_users']);
    $this->assertSame(7, $result['active_users']);
    $this->assertSame(3, $result['blocked_users']);
    $this->assertSame(2, $result['returned']);
    $this->assertCount(2, $result['users']);
  }

}
