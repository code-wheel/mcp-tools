<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_menus\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_menus\Service\MenuService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for deleting menu links.
 *
 * @Tool(
 *   id = "mcp_menus_delete_menu_link",
 *   label = @Translation("Delete Menu Link"),
 *   description = @Translation("Delete a menu link."),
 *   category = "menus",
 * )
 */
class DeleteMenuLink extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected MenuService $menuService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->menuService = $container->get('mcp_tools_menus.menu');
    return $instance;
  }

  public function execute(array $input = []): array {
    $linkId = $input['link_id'] ?? 0;

    if (empty($linkId)) {
      return ['success' => FALSE, 'error' => 'Link ID is required.'];
    }

    return $this->menuService->deleteMenuLink((int) $linkId);
  }

  public function getInputDefinition(): array {
    return [
      'link_id' => ['type' => 'integer', 'label' => 'Link ID', 'required' => TRUE, 'description' => 'Menu link content entity ID'],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'integer', 'label' => 'Deleted Link ID'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
