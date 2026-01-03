<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_menus\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_menus\Service\MenuService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for updating menu links.
 *
 * @Tool(
 *   id = "mcp_menus_update_menu_link",
 *   label = @Translation("Update Menu Link"),
 *   description = @Translation("Update an existing menu link's title, URL, weight, or other properties."),
 *   category = "menus",
 * )
 */
class UpdateMenuLink extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    $updates = $input['updates'] ?? [];

    if (empty($updates)) {
      return ['success' => FALSE, 'error' => 'At least one field to update is required.'];
    }

    return $this->menuService->updateMenuLink((int) $linkId, $updates);
  }

  public function getInputDefinition(): array {
    return [
      'link_id' => ['type' => 'integer', 'label' => 'Link ID', 'required' => TRUE, 'description' => 'Menu link content entity ID'],
      'updates' => ['type' => 'object', 'label' => 'Updates', 'required' => TRUE, 'description' => 'Fields to update: title, uri, weight, expanded, description'],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'integer', 'label' => 'Link ID'],
      'title' => ['type' => 'string', 'label' => 'Title'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
