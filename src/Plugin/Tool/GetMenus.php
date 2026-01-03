<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\MenuService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting all menus.
 *
 * @Tool(
 *   id = "mcp_tools_get_menus",
 *   label = @Translation("Get Menus"),
 *   description = @Translation("Get all menus defined on the site with link counts."),
 *   category = "structure",
 * )
 */
class GetMenus extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return [
      'success' => TRUE,
      'data' => $this->menuService->getMenus(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total_menus' => [
        'type' => 'integer',
        'label' => 'Total Menus',
      ],
      'menus' => [
        'type' => 'list',
        'label' => 'Menus',
      ],
    ];
  }

}
