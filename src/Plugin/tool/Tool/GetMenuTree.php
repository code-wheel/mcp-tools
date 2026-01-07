<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\MenuService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_tools_get_menu_tree',
  label: new TranslatableMarkup('Get Menu Tree'),
  description: new TranslatableMarkup('Get the hierarchical structure of a specific menu.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'menu' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Menu Name'),
      description: new TranslatableMarkup('The menu machine name (e.g., "main", "footer", "admin").'),
      required: TRUE,
    ),
    'max_depth' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Max Depth'),
      description: new TranslatableMarkup('Maximum depth of menu tree to return. Max 10.'),
      required: FALSE,
      default_value: 5,
    ),
  ],
  output_definitions: [
    'menu' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Menu Name'),
      description: new TranslatableMarkup('Machine name of the menu queried.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Menu Label'),
      description: new TranslatableMarkup('Human-readable menu title.'),
    ),
    'tree' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Menu Tree'),
      description: new TranslatableMarkup('Hierarchical array of menu links. Each has id, title, url, weight, enabled, and children (nested links).'),
    ),
  ],
)]
class GetMenuTree extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';


  protected MenuService $menuService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->menuService = $container->get('mcp_tools.menu');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
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

  

  

}
