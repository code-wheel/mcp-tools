<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_menus\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_menus\Service\MenuService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for creating menus.
 *
 * @Tool(
 *   id = "mcp_menus_create_menu",
 *   label = @Translation("Create Menu"),
 *   description = @Translation("Create a new menu."),
 *   category = "menus",
 * )
 */
class CreateMenu extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected MenuService $menuService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->menuService = $container->get('mcp_tools_menus.menu');
    return $instance;
  }

  public function execute(array $input = []): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($id) || empty($label)) {
      return ['success' => FALSE, 'error' => 'Both id and label are required.'];
    }

    return $this->menuService->createMenu($id, $label, $input['description'] ?? '');
  }

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Machine Name', 'required' => TRUE, 'description' => 'Lowercase, underscores, hyphens (max 32 chars)'],
      'label' => ['type' => 'string', 'label' => 'Label', 'required' => TRUE, 'description' => 'Human-readable name'],
      'description' => ['type' => 'string', 'label' => 'Description', 'required' => FALSE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Menu ID'],
      'label' => ['type' => 'string', 'label' => 'Menu Label'],
      'admin_path' => ['type' => 'string', 'label' => 'Admin Path'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
