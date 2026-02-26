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
  id: 'mcp_menus_create_menu',
  label: new TranslatableMarkup('Create Menu'),
  description: new TranslatableMarkup('Create a new menu.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Machine Name'),
      description: new TranslatableMarkup('Lowercase, underscores, hyphens (max 32 chars)'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name'),
      required: TRUE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup('Administrative description. Shown in menu listing.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Menu ID'),
      description: new TranslatableMarkup('Machine name of the created menu. Use with AddMenuLink, GetMenuTree.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Menu Label'),
      description: new TranslatableMarkup('Human-readable menu name.'),
    ),
    'admin_path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Admin Path'),
      description: new TranslatableMarkup('Path to manage this menu in Drupal admin UI.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success confirmation or error details.'),
    ),
  ],
)]
class CreateMenu extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'menus';
  protected const MCP_WRITE_KIND = 'config';


  /**
   * The menu service.
   *
   * @var \Drupal\mcp_tools_menus\Service\MenuManagementService
   */
  protected MenuManagementService $menuService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->menuService = $container->get('mcp_tools_menus.menu');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($id) || empty($label)) {
      return ['success' => FALSE, 'error' => 'Both id and label are required.'];
    }

    return $this->menuService->createMenu($id, $label, $input['description'] ?? '');
  }

}
