<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\MenuService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting menu tree structure.
 *
 * @Tool(
 *   id = "mcp_tools_get_menu_tree",
 *   label = @Translation("Get Menu Tree"),
 *   description = @Translation("Get the hierarchical structure of a specific menu."),
 *   category = "structure",
 * )
 */
class GetMenuTree extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected MenuService $menuService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->menuService = $container->get('mcp_tools.menu');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $menuName = $input['menu'] ?? '';
    $maxDepth = min($input['max_depth'] ?? 5, 10);

    if (empty($menuName)) {
      return [
        'success' => FALSE,
        'error' => 'Menu name is required.',
      ];
    }

    $data = $this->menuService->getMenuTree($menuName, $maxDepth);

    if (isset($data['error'])) {
      return [
        'success' => FALSE,
        'error' => $data['error'],
      ];
    }

    return [
      'success' => TRUE,
      'data' => $data,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'menu' => [
        'type' => 'string',
        'label' => 'Menu Name',
        'description' => 'The menu machine name (e.g., "main", "footer", "admin").',
        'required' => TRUE,
      ],
      'max_depth' => [
        'type' => 'integer',
        'label' => 'Max Depth',
        'description' => 'Maximum depth of menu tree to return. Max 10.',
        'required' => FALSE,
        'default' => 5,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'menu' => [
        'type' => 'string',
        'label' => 'Menu Name',
      ],
      'label' => [
        'type' => 'string',
        'label' => 'Menu Label',
      ],
      'tree' => [
        'type' => 'list',
        'label' => 'Menu Tree',
      ],
    ];
  }

}
