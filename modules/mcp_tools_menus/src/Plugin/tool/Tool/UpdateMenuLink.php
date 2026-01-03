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
  id: 'mcp_menus_update_menu_link',
  label: new TranslatableMarkup('Update Menu Link'),
  description: new TranslatableMarkup('Update an existing menu link\'s title, URL, weight, or other properties.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'link_id' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Link ID'),
      description: new TranslatableMarkup('Menu link content entity ID'),
      required: TRUE,
    ),
    'updates' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Updates'),
      description: new TranslatableMarkup('Fields to update: title, uri, weight, expanded, description'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Link ID'),
      description: new TranslatableMarkup(''),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class UpdateMenuLink extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'menus';


  protected MenuService $menuService;

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

    $updates = $input['updates'] ?? [];

    if (empty($updates)) {
      return ['success' => FALSE, 'error' => 'At least one field to update is required.'];
    }

    return $this->menuService->updateMenuLink((int) $linkId, $updates);
  }


}
