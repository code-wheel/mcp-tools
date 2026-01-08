<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\mcp_tools\Service\MenuService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\MenuService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class MenuServiceTest extends UnitTestCase {

  public function testGetMenuTreeReturnsErrorWhenMenuMissing(): void {
    $menuStorage = $this->createMock(EntityStorageInterface::class);
    $menuStorage->method('load')->with('main')->willReturn(NULL);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('menu')->willReturn($menuStorage);

    $service = new MenuService($entityTypeManager, $this->createMock(MenuLinkTreeInterface::class));
    $result = $service->getMenuTree('main');

    $this->assertArrayHasKey('error', $result);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testGetMenusCountsLinksRecursively(): void {
    $menu1 = new class() {
      public function id(): string { return 'main'; }
      public function label(): string { return 'Main navigation'; }
      public function getDescription(): string { return 'Main menu'; }
    };

    $menuStorage = $this->createMock(EntityStorageInterface::class);
    $menuStorage->method('loadMultiple')->willReturn(['main' => $menu1]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('menu')->willReturn($menuStorage);

    $link = new class() {
      public function getTitle(): string { return 'Home'; }
      public function getUrlObject(): object { return new class() { public function toString(): string { return '/'; } }; }
      public function isEnabled(): bool { return TRUE; }
      public function isExpanded(): bool { return FALSE; }
      public function getWeight(): int { return 0; }
    };

    $child = (object) ['link' => $link, 'hasChildren' => FALSE, 'subtree' => []];
    $parent = (object) ['link' => $link, 'hasChildren' => TRUE, 'subtree' => [$child]];

    $tree = [$parent];

    $menuLinkTree = $this->createMock(MenuLinkTreeInterface::class);
    $menuLinkTree->method('load')->willReturn($tree);

    $service = new MenuService($entityTypeManager, $menuLinkTree);
    $result = $service->getMenus();

    $this->assertSame(1, $result['total_menus']);
    $this->assertSame(2, $result['menus'][0]['link_count']);
  }

  public function testGetMenuTreeBuildsNestedArray(): void {
    $menu = new class() {
      public function label(): string { return 'Main navigation'; }
    };

    $menuStorage = $this->createMock(EntityStorageInterface::class);
    $menuStorage->method('load')->with('main')->willReturn($menu);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('menu')->willReturn($menuStorage);

    $childLink = new class('Child', '/child') {
      public function __construct(private readonly string $title, private readonly string $url) {}
      public function getTitle(): string { return $this->title; }
      public function getUrlObject(): object {
        return new class($this->url) {
          public function __construct(private readonly string $url) {}
          public function toString(): string { return $this->url; }
        };
      }
      public function isEnabled(): bool { return TRUE; }
      public function isExpanded(): bool { return FALSE; }
      public function getWeight(): int { return 0; }
    };

    $parentLink = new class('Parent', '/parent') {
      public function __construct(private readonly string $title, private readonly string $url) {}
      public function getTitle(): string { return $this->title; }
      public function getUrlObject(): object {
        return new class($this->url) {
          public function __construct(private readonly string $url) {}
          public function toString(): string { return $this->url; }
        };
      }
      public function isEnabled(): bool { return TRUE; }
      public function isExpanded(): bool { return FALSE; }
      public function getWeight(): int { return 0; }
    };

    $child = (object) ['link' => $childLink, 'hasChildren' => FALSE, 'subtree' => []];
    $parent = (object) ['link' => $parentLink, 'hasChildren' => TRUE, 'subtree' => [$child]];

    $menuLinkTree = $this->createMock(MenuLinkTreeInterface::class);
    $menuLinkTree->method('load')->willReturn([$parent]);
    $menuLinkTree->method('transform')->willReturnCallback(static fn(array $tree): array => $tree);

    $service = new MenuService($entityTypeManager, $menuLinkTree);
    $result = $service->getMenuTree('main', 3);

    $this->assertSame('main', $result['menu']);
    $this->assertSame('Main navigation', $result['label']);
    $this->assertSame('Parent', $result['tree'][0]['title']);
    $this->assertSame('Child', $result['tree'][0]['children'][0]['title']);
  }

}
