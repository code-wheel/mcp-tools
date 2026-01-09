<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_menus\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_menus\Service\MenuManagementService;
use Drupal\system\MenuInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for MenuManagementService.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_menus\Service\MenuManagementService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_menus')]
class MenuManagementServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected MenuLinkManagerInterface $menuLinkManager;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $menuStorage;
  protected EntityStorageInterface $menuLinkStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->menuLinkManager = $this->createMock(MenuLinkManagerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);

    $this->menuStorage = $this->createMock(EntityStorageInterface::class);
    $this->menuLinkStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['menu', $this->menuStorage],
        ['menu_link_content', $this->menuLinkStorage],
      ]);
  }

  /**
   * Creates a MenuManagementService instance.
   */
  protected function createMenuManagementService(): MenuManagementService {
    return new MenuManagementService(
      $this->entityTypeManager,
      $this->menuLinkManager,
      $this->accessManager,
      $this->auditLogger
    );
  }

  public function testCreateMenuAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createMenuManagementService();
    $result = $service->createMenu('custom_menu', 'Custom Menu');

    $this->assertFalse($result['success']);
  }

  #[\PHPUnit\Framework\Attributes\DataProvider('invalidMenuMachineNameProvider')]
  public function testCreateMenuInvalidMachineName(string $invalidName): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createMenuManagementService();
    $result = $service->createMenu($invalidName, 'Invalid Menu');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid machine name', $result['error']);
  }

  /**
   * Data provider for invalid menu machine names.
   */
  public static function invalidMenuMachineNameProvider(): array {
    return [
      'starts with number' => ['1menu'],
      'uppercase letters' => ['MyMenu'],
      'has spaces' => ['my menu'],
      'special chars' => ['menu@test'],
    ];
  }

  public function testCreateMenuMachineNameTooLong(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createMenuManagementService();
    $result = $service->createMenu(str_repeat('a', 33), 'Long Menu');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('32 characters', $result['error']);
  }

  public function testCreateMenuAlreadyExists(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $existingMenu = $this->createMock(MenuInterface::class);
    $this->menuStorage->method('load')->with('existing_menu')->willReturn($existingMenu);

    $service = $this->createMenuManagementService();
    $result = $service->createMenu('existing_menu', 'Existing Menu');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  public function testDeleteMenuAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createMenuManagementService();
    $result = $service->deleteMenu('custom_menu');

    $this->assertFalse($result['success']);
  }

  #[\PHPUnit\Framework\Attributes\DataProvider('protectedMenuProvider')]
  public function testDeleteMenuProtectsSystemMenus(string $protectedMenu): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createMenuManagementService();
    $result = $service->deleteMenu($protectedMenu);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Cannot delete system menu', $result['error']);
    $this->assertStringContainsString($protectedMenu, $result['error']);
  }

  /**
   * Data provider for protected menus.
   */
  public static function protectedMenuProvider(): array {
    return [
      'admin menu' => ['admin'],
      'tools menu' => ['tools'],
      'account menu' => ['account'],
      'main menu' => ['main'],
      'footer menu' => ['footer'],
    ];
  }

  public function testDeleteMenuNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->menuStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $service = $this->createMenuManagementService();
    $result = $service->deleteMenu('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testAddMenuLinkAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createMenuManagementService();
    $result = $service->addMenuLink('main', 'Test Link', '/node/1');

    $this->assertFalse($result['success']);
  }

  public function testAddMenuLinkMenuNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->menuStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $service = $this->createMenuManagementService();
    $result = $service->addMenuLink('nonexistent', 'Test Link', '/node/1');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testUpdateMenuLinkAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createMenuManagementService();
    $result = $service->updateMenuLink(1, ['title' => 'New Title']);

    $this->assertFalse($result['success']);
  }

  public function testUpdateMenuLinkNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->menuLinkStorage->method('load')->with(999)->willReturn(NULL);

    $service = $this->createMenuManagementService();
    $result = $service->updateMenuLink(999, ['title' => 'New Title']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testDeleteMenuLinkAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createMenuManagementService();
    $result = $service->deleteMenuLink(1);

    $this->assertFalse($result['success']);
  }

  public function testDeleteMenuLinkNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->menuLinkStorage->method('load')->with(999)->willReturn(NULL);

    $service = $this->createMenuManagementService();
    $result = $service->deleteMenuLink(999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

}
