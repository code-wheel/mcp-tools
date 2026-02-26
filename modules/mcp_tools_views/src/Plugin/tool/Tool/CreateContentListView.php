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
  id: 'mcp_create_content_list_view',
  label: new TranslatableMarkup('Create Content List View'),
  description: new TranslatableMarkup('Create a content listing view with sensible defaults.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Machine Name'),
      description: new TranslatableMarkup('Unique view identifier. Lowercase, underscores only.'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name shown in admin UI and as page title.'),
      required: TRUE,
    ),
    'content_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type Filter'),
      description: new TranslatableMarkup('Filter to specific content type machine name (e.g., "article"). Omit for all types.'),
      required: FALSE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup('Administrative description shown in views listing.'),
      required: FALSE,
    ),
    'page_path' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Page Path'),
      description: new TranslatableMarkup('URL path for page display (e.g., /articles). Creates page display if provided.'),
      required: FALSE,
    ),
    'block' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Create Block Display'),
      description: new TranslatableMarkup('Set true to create a block display. Use PlaceBlock to position.'),
      required: FALSE,
    ),
    'block_items' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Block Items Count'),
      description: new TranslatableMarkup('Number of items in block display. Defaults to 5.'),
      required: FALSE,
    ),
    'items_per_page' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Items Per Page'),
      description: new TranslatableMarkup('Number of items per page for page display. 0 = all. Defaults to 10.'),
      required: FALSE,
    ),
    'sort' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Sort Option'),
      description: new TranslatableMarkup('Sort order: "newest", "oldest", "title", "changed". Defaults to newest.'),
      required: FALSE,
    ),
    'show_title' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Show Title'),
      description: new TranslatableMarkup('Show node title as clickable link. Defaults to true.'),
      required: FALSE,
    ),
    'fields' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Fields to Display'),
      description: new TranslatableMarkup('Field machine names to display (e.g., ["body", "field_image"]). Title always included.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('View ID'),
      description: new TranslatableMarkup('Machine name of created view. Use with AddViewDisplay, EnableView, DeleteView.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('View Label'),
      description: new TranslatableMarkup('Human-readable view name.'),
    ),
    'base_table' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Base Table'),
      description: new TranslatableMarkup('Data source (node_field_data for content lists).'),
    ),
    'displays' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Display IDs'),
      description: new TranslatableMarkup('List of created display IDs (default, page_1, block_1).'),
    ),
    'page_path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Page Path'),
      description: new TranslatableMarkup('URL path where page display is accessible.'),
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
class CreateContentListView extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'views';


  /**
   * The views service.
   *
   * @var \Drupal\mcp_tools_views\Service\ViewsService
   */
  protected ViewsService $viewsService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->viewsService = $container->get('mcp_tools_views.views');
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

    $contentType = $input['content_type'] ?? '';

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
    if (!empty($input['block_items'])) {
      $options['block_items'] = (int) $input['block_items'];
    }
    if (!empty($input['items_per_page'])) {
      $options['items_per_page'] = (int) $input['items_per_page'];
    }
    if (!empty($input['sort'])) {
      $options['sort'] = $input['sort'];
    }
    if (isset($input['show_title'])) {
      $options['show_title'] = (bool) $input['show_title'];
    }
    if (!empty($input['fields'])) {
      $options['fields'] = $input['fields'];
    }

    return $this->viewsService->createContentListView($id, $label, $contentType, $options);
  }

}
