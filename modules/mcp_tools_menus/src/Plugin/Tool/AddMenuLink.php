<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_menus\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_menus\Service\MenuService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for adding menu links.
 *
 * @Tool(
 *   id = "mcp_menus_add_menu_link",
 *   label = @Translation("Add Menu Link"),
 *   description = @Translation("Add a link to a menu with optional weight, parent, and expanded settings."),
 *   category = "menus",
 * )
 */
class AddMenuLink extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected MenuService $menuService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->menuService = $container->get('mcp_tools_menus.menu');
    return $instance;
  }

  public function execute(array $input = []): array {
    $menu = $input['menu'] ?? '';
    $title = $input['title'] ?? '';
    $uri = $input['uri'] ?? '';

    if (empty($menu) || empty($title) || empty($uri)) {
      return ['success' => FALSE, 'error' => 'Menu, title, and uri are required.'];
    }

    $options = [];
    if (isset($input['weight'])) {
      $options['weight'] = (int) $input['weight'];
    }
    if (isset($input['expanded'])) {
      $options['expanded'] = (bool) $input['expanded'];
    }
    if (!empty($input['description'])) {
      $options['description'] = $input['description'];
    }
    if (!empty($input['parent'])) {
      $options['parent'] = $input['parent'];
    }

    return $this->menuService->addMenuLink($menu, $title, $uri, $options);
  }

  public function getInputDefinition(): array {
    return [
      'menu' => ['type' => 'string', 'label' => 'Menu', 'required' => TRUE, 'description' => 'Menu machine name (e.g., "main", "footer")'],
      'title' => ['type' => 'string', 'label' => 'Title', 'required' => TRUE, 'description' => 'Link text'],
      'uri' => ['type' => 'string', 'label' => 'URI', 'required' => TRUE, 'description' => 'Path (/about) or URL (https://example.com)'],
      'weight' => ['type' => 'integer', 'label' => 'Weight', 'required' => FALSE, 'description' => 'Sort order (lower = higher)'],
      'expanded' => ['type' => 'boolean', 'label' => 'Expanded', 'required' => FALSE, 'description' => 'Show children expanded'],
      'description' => ['type' => 'string', 'label' => 'Description', 'required' => FALSE],
      'parent' => ['type' => 'string', 'label' => 'Parent', 'required' => FALSE, 'description' => 'Parent plugin ID for nesting'],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'integer', 'label' => 'Link ID'],
      'uuid' => ['type' => 'string', 'label' => 'UUID'],
      'title' => ['type' => 'string', 'label' => 'Title'],
      'menu' => ['type' => 'string', 'label' => 'Menu'],
      'plugin_id' => ['type' => 'string', 'label' => 'Plugin ID'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
