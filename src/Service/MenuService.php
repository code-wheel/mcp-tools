<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;

/**
 * Service for menu operations.
 */
class MenuService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MenuLinkTreeInterface $menuLinkTree,
  ) {}

  /**
   * Get all menus.
   *
   * @return array
   *   All menus with link counts.
   */
  public function getMenus(): array {
    $menuStorage = $this->entityTypeManager->getStorage('menu');
    $menus = $menuStorage->loadMultiple();

    $result = [];
    foreach ($menus as $menu) {
      $tree = $this->menuLinkTree->load($menu->id(), new MenuTreeParameters());

      $result[] = [
        'id' => $menu->id(),
        'label' => $menu->label(),
        'description' => $menu->getDescription(),
        'link_count' => $this->countLinks($tree),
      ];
    }

    return [
      'total_menus' => count($result),
      'menus' => $result,
    ];
  }

  /**
   * Get menu tree structure.
   *
   * @param string $menuName
   *   Menu machine name.
   * @param int $maxDepth
   *   Maximum depth to return.
   *
   * @return array
   *   Menu tree structure.
   */
  public function getMenuTree(string $menuName, int $maxDepth = 5): array {
    $menu = $this->entityTypeManager->getStorage('menu')->load($menuName);
    if (!$menu) {
      return [
        'error' => "Menu '$menuName' not found. Use mcp_list_menus to see available menus.",
      ];
    }

    $parameters = new MenuTreeParameters();
    $parameters->setMaxDepth($maxDepth);

    $tree = $this->menuLinkTree->load($menuName, $parameters);

    // Apply manipulators.
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);

    return [
      'menu' => $menuName,
      'label' => $menu->label(),
      'tree' => $this->buildTreeArray($tree),
    ];
  }

  /**
   * Build array representation of menu tree.
   *
   * @param array $tree
   *   Menu tree.
   *
   * @return array
   *   Array representation.
   */
  protected function buildTreeArray(array $tree): array {
    $result = [];

    foreach ($tree as $element) {
      $link = $element->link;
      $item = [
        'title' => $link->getTitle(),
        'url' => $link->getUrlObject()->toString(),
        'enabled' => $link->isEnabled(),
        'expanded' => $link->isExpanded(),
        'weight' => $link->getWeight(),
        'children' => [],
      ];

      if ($element->hasChildren) {
        $item['children'] = $this->buildTreeArray($element->subtree);
      }

      $result[] = $item;
    }

    return $result;
  }

  /**
   * Count links in tree.
   *
   * @param array $tree
   *   Menu tree.
   *
   * @return int
   *   Link count.
   */
  protected function countLinks(array $tree): int {
    $count = 0;
    foreach ($tree as $element) {
      $count++;
      if ($element->hasChildren) {
        $count += $this->countLinks($element->subtree);
      }
    }
    return $count;
  }

}
