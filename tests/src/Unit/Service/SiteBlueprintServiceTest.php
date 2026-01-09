<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\mcp_tools\Service\SiteBlueprintService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\SiteBlueprintService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class SiteBlueprintServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected EntityFieldManagerInterface $entityFieldManager;
  protected ModuleHandlerInterface $moduleHandler;
  protected ConfigFactoryInterface $configFactory;

  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);

    // Default module existence checks.
    $this->moduleHandler->method('moduleExists')
      ->willReturnCallback(fn($module) => in_array($module, ['taxonomy', 'views']));
  }

  protected function createService(): SiteBlueprintService {
    return new SiteBlueprintService(
      $this->entityTypeManager,
      $this->entityFieldManager,
      $this->moduleHandler,
      $this->configFactory,
    );
  }

  protected function setupEmptyStorages(): void {
    $emptyStorage = $this->createMock(EntityStorageInterface::class);
    $emptyStorage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')->willReturn($emptyStorage);
  }

  protected function setupThemeConfig(): void {
    $systemTheme = $this->createMock(ImmutableConfig::class);
    $systemTheme->method('get')->willReturnMap([
      ['default', 'olivero'],
      ['admin', 'claro'],
    ]);

    $coreExtension = $this->createMock(ImmutableConfig::class);
    $coreExtension->method('get')
      ->with('theme')
      ->willReturn(['olivero' => 0, 'claro' => 0]);

    $this->configFactory->method('get')->willReturnMap([
      ['system.theme', $systemTheme],
      ['core.extension', $coreExtension],
    ]);
  }

  public function testGetBlueprintReturnsAllSections(): void {
    $this->setupEmptyStorages();
    $this->setupThemeConfig();

    $service = $this->createService();
    $blueprint = $service->getBlueprint();

    $this->assertArrayHasKey('content_types', $blueprint);
    $this->assertArrayHasKey('vocabularies', $blueprint);
    $this->assertArrayHasKey('roles', $blueprint);
    $this->assertArrayHasKey('views', $blueprint);
    $this->assertArrayHasKey('menus', $blueprint);
    $this->assertArrayHasKey('themes', $blueprint);
  }

  public function testGetBlueprintContentTypesEmpty(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')
      ->willReturn($storage);
    $this->setupThemeConfig();

    $service = $this->createService();
    $blueprint = $service->getBlueprint();

    $this->assertSame(0, $blueprint['content_types']['total']);
    $this->assertEmpty($blueprint['content_types']['items']);
  }

  public function testGetBlueprintContentTypesWithTypes(): void {
    $type1 = $this->createMock(\Drupal\node\NodeTypeInterface::class);
    $type1->method('id')->willReturn('article');
    $type1->method('label')->willReturn('Article');
    $type1->method('getDescription')->willReturn('Use articles for blog posts');

    $type2 = $this->createMock(\Drupal\node\NodeTypeInterface::class);
    $type2->method('id')->willReturn('page');
    $type2->method('label')->willReturn('Basic page');
    $type2->method('getDescription')->willReturn('');

    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('loadMultiple')->willReturn([
      'article' => $type1,
      'page' => $type2,
    ]);

    $emptyStorage = $this->createMock(EntityStorageInterface::class);
    $emptyStorage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($type) use ($nodeTypeStorage, $emptyStorage) {
        return $type === 'node_type' ? $nodeTypeStorage : $emptyStorage;
      });

    $this->entityFieldManager->method('getFieldDefinitions')->willReturn([]);
    $this->setupThemeConfig();

    $service = $this->createService();
    $blueprint = $service->getBlueprint();

    $this->assertSame(2, $blueprint['content_types']['total']);
    $this->assertCount(2, $blueprint['content_types']['items']);

    // Verify sorted alphabetically by label.
    $this->assertSame('article', $blueprint['content_types']['items'][0]['id']);
    $this->assertSame('page', $blueprint['content_types']['items'][1]['id']);
  }

  public function testGetBlueprintRolesWithPermissionCount(): void {
    $editorRole = $this->createMock(\Drupal\user\RoleInterface::class);
    $editorRole->method('id')->willReturn('editor');
    $editorRole->method('label')->willReturn('Editor');
    $editorRole->method('getPermissions')->willReturn(['edit any article content', 'delete any article content']);

    $adminRole = $this->createMock(\Drupal\user\RoleInterface::class);
    $adminRole->method('id')->willReturn('administrator');
    $adminRole->method('label')->willReturn('Administrator');
    $adminRole->method('getPermissions')->willReturn(['administer site configuration']);

    $roleStorage = $this->createMock(EntityStorageInterface::class);
    $roleStorage->method('loadMultiple')->willReturn([
      'editor' => $editorRole,
      'administrator' => $adminRole,
    ]);

    $emptyStorage = $this->createMock(EntityStorageInterface::class);
    $emptyStorage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($type) use ($roleStorage, $emptyStorage) {
        return $type === 'user_role' ? $roleStorage : $emptyStorage;
      });

    $this->setupThemeConfig();

    $service = $this->createService();
    $blueprint = $service->getBlueprint();

    $this->assertSame(2, $blueprint['roles']['total']);

    // Find editor role.
    $editor = array_values(array_filter(
      $blueprint['roles']['items'],
      fn($r) => $r['id'] === 'editor'
    ))[0];

    $this->assertSame(2, $editor['permission_count']);
  }

  public function testGetBlueprintVocabulariesWhenTaxonomyDisabled(): void {
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->moduleHandler->method('moduleExists')
      ->willReturnCallback(fn($module) => $module !== 'taxonomy');

    $this->setupEmptyStorages();
    $this->setupThemeConfig();

    $service = $this->createService();
    $blueprint = $service->getBlueprint();

    $this->assertSame(0, $blueprint['vocabularies']['total']);
    $this->assertArrayHasKey('note', $blueprint['vocabularies']);
    $this->assertStringContainsString('disabled', $blueprint['vocabularies']['note']);
  }

  public function testGetBlueprintViewsWhenViewsDisabled(): void {
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->moduleHandler->method('moduleExists')
      ->willReturnCallback(fn($module) => $module !== 'views');

    $this->setupEmptyStorages();
    $this->setupThemeConfig();

    $service = $this->createService();
    $blueprint = $service->getBlueprint();

    $this->assertSame(0, $blueprint['views']['total']);
    $this->assertArrayHasKey('note', $blueprint['views']);
    $this->assertStringContainsString('disabled', $blueprint['views']['note']);
  }

  public function testGetBlueprintThemes(): void {
    $this->setupEmptyStorages();
    $this->setupThemeConfig();

    $service = $this->createService();
    $blueprint = $service->getBlueprint();

    $this->assertSame('olivero', $blueprint['themes']['default']);
    $this->assertSame('claro', $blueprint['themes']['admin']);
    $this->assertContains('olivero', $blueprint['themes']['enabled']);
    $this->assertContains('claro', $blueprint['themes']['enabled']);
    $this->assertSame(2, $blueprint['themes']['total_enabled']);
  }

  public function testGetBlueprintMenus(): void {
    $mainMenu = $this->createMock(\Drupal\system\MenuInterface::class);
    $mainMenu->method('id')->willReturn('main');
    $mainMenu->method('label')->willReturn('Main navigation');

    $footerMenu = $this->createMock(\Drupal\system\MenuInterface::class);
    $footerMenu->method('id')->willReturn('footer');
    $footerMenu->method('label')->willReturn('Footer');

    $menuStorage = $this->createMock(EntityStorageInterface::class);
    $menuStorage->method('loadMultiple')->willReturn([
      'main' => $mainMenu,
      'footer' => $footerMenu,
    ]);

    $emptyStorage = $this->createMock(EntityStorageInterface::class);
    $emptyStorage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($type) use ($menuStorage, $emptyStorage) {
        return $type === 'menu' ? $menuStorage : $emptyStorage;
      });

    $this->setupThemeConfig();

    $service = $this->createService();
    $blueprint = $service->getBlueprint();

    $this->assertSame(2, $blueprint['menus']['total']);

    // Verify sorted alphabetically.
    $this->assertSame('footer', $blueprint['menus']['items'][0]['id']);
    $this->assertSame('main', $blueprint['menus']['items'][1]['id']);
  }

  public function testGetBlueprintHandlesStorageExceptions(): void {
    $failingStorage = $this->createMock(EntityStorageInterface::class);
    $failingStorage->method('loadMultiple')
      ->willThrowException(new \Exception('Storage unavailable'));

    $this->entityTypeManager->method('getStorage')->willReturn($failingStorage);
    $this->setupThemeConfig();

    $service = $this->createService();
    $blueprint = $service->getBlueprint();

    // Should contain error messages instead of crashing.
    $this->assertArrayHasKey('error', $blueprint['content_types']);
    $this->assertStringContainsString('Unable to load', $blueprint['content_types']['error']);
  }

  public function testGetBlueprintViewsWithStatus(): void {
    $enabledView = $this->createMock(\Drupal\views\ViewEntityInterface::class);
    $enabledView->method('id')->willReturn('content');
    $enabledView->method('label')->willReturn('Content');
    $enabledView->method('status')->willReturn(TRUE);
    $enabledView->method('get')->with('base_table')->willReturn('node_field_data');

    $disabledView = $this->createMock(\Drupal\views\ViewEntityInterface::class);
    $disabledView->method('id')->willReturn('archive');
    $disabledView->method('label')->willReturn('Archive');
    $disabledView->method('status')->willReturn(FALSE);
    $disabledView->method('get')->with('base_table')->willReturn('node_field_data');

    $viewStorage = $this->createMock(EntityStorageInterface::class);
    $viewStorage->method('loadMultiple')->willReturn([
      'content' => $enabledView,
      'archive' => $disabledView,
    ]);

    $emptyStorage = $this->createMock(EntityStorageInterface::class);
    $emptyStorage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($type) use ($viewStorage, $emptyStorage) {
        return $type === 'view' ? $viewStorage : $emptyStorage;
      });

    $this->setupThemeConfig();

    $service = $this->createService();
    $blueprint = $service->getBlueprint();

    $this->assertSame(2, $blueprint['views']['total']);

    $contentView = array_values(array_filter(
      $blueprint['views']['items'],
      fn($v) => $v['id'] === 'content'
    ))[0];

    $archiveView = array_values(array_filter(
      $blueprint['views']['items'],
      fn($v) => $v['id'] === 'archive'
    ))[0];

    $this->assertSame('enabled', $contentView['status']);
    $this->assertSame('disabled', $archiveView['status']);
    $this->assertSame('node_field_data', $contentView['base_table']);
  }

}
