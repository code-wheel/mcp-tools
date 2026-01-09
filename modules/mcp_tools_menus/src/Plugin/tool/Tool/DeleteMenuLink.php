<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_menus\Plugin\tool\Tool;

use Drupal\mcp_tools_menus\Service\MenuManagementService;
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
  id: 'mcp_menus_delete_menu_link',
  label: new TranslatableMarkup('Delete Menu Link'),
  description: new TranslatableMarkup('Delete a menu link.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'link_id' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Link ID'),
      description: new TranslatableMarkup('Menu link content entity ID'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Deleted Link ID'),
      description: new TranslatableMarkup('ID of the menu link that was deleted.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success confirmation. Child links are NOT automatically deleted.'),
    ),
  ],
)]
class DeleteMenuLink extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'menus';


  protected MenuManagementService $menuService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->menuService = $container->get('mcp_tools_menus.menu');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $linkId = $input['link_id'] ?? 0;

    if (empty($linkId)) {
      return ['success' => FALSE, 'error' => 'Link ID is required.'];
    }

    return $this->menuService->deleteMenuLink((int) $linkId);
  }


}
