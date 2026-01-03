<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_menus\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_menus\Service\MenuService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for deleting menus.
 *
 * @Tool(
 *   id = "mcp_menus_delete_menu",
 *   label = @Translation("Delete Menu"),
 *   description = @Translation("Delete a custom menu. System menus (admin, tools, account, main, footer) cannot be deleted."),
 *   category = "menus",
 * )
 */
class DeleteMenu extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected MenuService $menuService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->menuService = $container->get('mcp_tools_menus.menu');
    return $instance;
  }

  public function execute(array $input = []): array {
    $id = $input['id'] ?? '';

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Menu id is required.'];
    }

    return $this->menuService->deleteMenu($id);
  }

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Menu ID', 'required' => TRUE, 'description' => 'Machine name of the menu to delete'],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Deleted Menu ID'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
