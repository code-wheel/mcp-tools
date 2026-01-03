<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_menus\Plugin\tool\Tool;

use Drupal\mcp_tools_menus\Service\MenuService;
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
  id: 'mcp_menus_delete_menu',
  label: new TranslatableMarkup('Delete Menu'),
  description: new TranslatableMarkup('Delete a custom menu. System menus (admin, tools, account, main, footer) cannot be deleted.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Menu ID'),
      description: new TranslatableMarkup('Machine name of the menu to delete'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Deleted Menu ID'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class DeleteMenu extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'menus';


  protected MenuService $menuService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->menuService = $container->get('mcp_tools_menus.menu');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Menu id is required.'];
    }

    return $this->menuService->deleteMenu($id);
  }


}
