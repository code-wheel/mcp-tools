<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_views\Plugin\tool\Tool;

use Drupal\mcp_tools_views\Service\ViewsService;
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
  id: 'mcp_create_view',
  label: new TranslatableMarkup('Create View'),
  description: new TranslatableMarkup('Create a new view with optional page and block displays.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Machine Name'),
      description: new TranslatableMarkup('Unique view identifier. Lowercase, underscores only. Used in URLs and config.'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name shown in admin UI.'),
      required: TRUE,
    ),
    'base_table' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Base Table'),
      description: new TranslatableMarkup('Data source: node_field_data (content), users_field_data (users), taxonomy_term_field_data (terms). Defaults to node_field_data.'),
      required: FALSE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup('Administrative description. Shown in views listing.'),
      required: FALSE,
    ),
    'page_path' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Page Path'),
      description: new TranslatableMarkup('URL path for page display (e.g., /my-list). Creates a page display if provided.'),
      required: FALSE,
    ),
    'block' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Create Block Display'),
      description: new TranslatableMarkup('Set true to create a block display. Place with PlaceBlock tool.'),
      required: FALSE,
    ),
    'items_per_page' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Items Per Page'),
      description: new TranslatableMarkup('Number of items per page. 0 = show all. Defaults to 10.'),
      required: FALSE,
    ),
    'sort' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Sort Option'),
      description: new TranslatableMarkup('Sort order: "newest" (created DESC), "oldest" (created ASC), "title" (title ASC), "changed" (changed DESC).'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('View ID'),
      description: new TranslatableMarkup('Machine name of the created view. Use with AddViewDisplay, EnableView, DeleteView.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('View Label'),
      description: new TranslatableMarkup('Human-readable view name.'),
    ),
    'base_table' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Base Table'),
      description: new TranslatableMarkup('Data source table used by this view.'),
    ),
    'displays' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Display IDs'),
      description: new TranslatableMarkup('List of created display IDs (default, page_1, block_1, etc.).'),
    ),
    'admin_path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Admin Path'),
      description: new TranslatableMarkup('Path to edit this view in Drupal admin UI.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success confirmation or error details.'),
    ),
  ],
)]
class CreateView extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'views';


  protected ViewsService $viewsService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->viewsService = $container->get('mcp_tools_views.views');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($id) || empty($label)) {
      return ['success' => FALSE, 'error' => 'Both id and label are required.'];
    }

    $baseTable = $input['base_table'] ?? 'node_field_data';

    $options = [];
    if (!empty($input['description'])) {
      $options['description'] = $input['description'];
    }
    if (!empty($input['page_path'])) {
      $options['page_path'] = $input['page_path'];
    }
    if (!empty($input['block'])) {
      $options['block'] = (bool) $input['block'];
    }
    if (!empty($input['items_per_page'])) {
      $options['items_per_page'] = (int) $input['items_per_page'];
    }
    if (!empty($input['sort'])) {
      $options['sort'] = $input['sort'];
    }

    return $this->viewsService->createView($id, $label, $baseTable, $options);
  }


}
