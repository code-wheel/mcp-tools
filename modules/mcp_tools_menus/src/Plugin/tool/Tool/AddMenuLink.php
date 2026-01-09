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
  id: 'mcp_menus_add_menu_link',
  label: new TranslatableMarkup('Add Menu Link'),
  description: new TranslatableMarkup('Add a link to a menu with optional weight, parent, and expanded settings.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'menu' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Menu'),
      description: new TranslatableMarkup('Menu machine name (e.g., "main", "footer")'),
      required: TRUE,
    ),
    'title' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('Link text'),
      required: TRUE,
    ),
    'uri' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('URI'),
      description: new TranslatableMarkup('Path (/about) or URL (https://example.com)'),
      required: TRUE,
    ),
    'weight' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Weight'),
      description: new TranslatableMarkup('Sort order (lower = higher)'),
      required: FALSE,
    ),
    'expanded' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Expanded'),
      description: new TranslatableMarkup('Show children expanded'),
      required: FALSE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup('Tooltip text shown on hover. Optional accessibility aid.'),
      required: FALSE,
    ),
    'parent' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Parent'),
      description: new TranslatableMarkup('Parent plugin ID for nesting'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Link ID'),
      description: new TranslatableMarkup('Menu link content entity ID. Use with UpdateMenuLink or DeleteMenuLink.'),
    ),
    'uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('UUID'),
      description: new TranslatableMarkup('Universally unique identifier for config export/import.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('The link text as created.'),
    ),
    'menu' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Menu'),
      description: new TranslatableMarkup('Menu machine name where link was added.'),
    ),
    'plugin_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Plugin ID'),
      description: new TranslatableMarkup('Internal plugin ID (menu_link_content:UUID). Use as parent value to nest links under this one.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success confirmation or error details.'),
    ),
  ],
)]
class AddMenuLink extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'menus';


  protected MenuManagementService $menuService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->menuService = $container->get('mcp_tools_menus.menu');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $menu = $input['menu'] ?? '';
    $title = $input['title'] ?? '';
    $uri = $input['uri'] ?? '';

    if (empty($menu) || empty($title) || empty($uri)) {
      return ['success' => FALSE, 'error' => 'Menu, title, and uri are required.'];
    }

    $options = [];
    if (isset($input['weight'])) {
      $options['weight'] = (int) $input['weight'];
    }
    if (isset($input['expanded'])) {
      $options['expanded'] = (bool) $input['expanded'];
    }
    if (!empty($input['description'])) {
      $options['description'] = $input['description'];
    }
    if (!empty($input['parent'])) {
      $options['parent'] = $input['parent'];
    }

    return $this->menuService->addMenuLink($menu, $title, $uri, $options);
  }


}
