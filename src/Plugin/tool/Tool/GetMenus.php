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

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_tools_get_menus',
  label: new TranslatableMarkup('Get Menus'),
  description: new TranslatableMarkup('Get all menus defined on the site with link counts.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total_menus' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Menus'),
      description: new TranslatableMarkup('Number of menus on the site.'),
    ),
    'menus' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Menus'),
      description: new TranslatableMarkup('Array of menus with id (machine name), label, description, and link_count. Use id with GetMenuTree or AddMenuLink.'),
    ),
  ],
)]
class GetMenus extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';


  /**
   * The menu service.
   *
   * @var \Drupal\mcp_tools\Service\MenuService
   */
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
    return [
      'success' => TRUE,
      'data' => $this->menuService->getMenus(),
    ];
  }

}
