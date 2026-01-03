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
  id: 'mcp_add_view_display',
  label: new TranslatableMarkup('Add View Display'),
  description: new TranslatableMarkup('Add a display (page, block, feed) to an existing view.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'view_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('View Machine Name'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'display_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Display Type (page, block, feed)'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'path' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Page Path (for page displays)'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'title' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Display Title'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'items_per_page' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Items Per Page'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'view_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('View ID'),
      description: new TranslatableMarkup(''),
    ),
    'display_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Display ID'),
      description: new TranslatableMarkup(''),
    ),
    'display_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Display Type'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class AddViewDisplay extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'views';


  protected ViewsService $viewsService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->viewsService = $container->get('mcp_tools_views.views');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $viewId = $input['view_id'] ?? '';
    $displayType = $input['display_type'] ?? '';

    if (empty($viewId) || empty($displayType)) {
      return ['success' => FALSE, 'error' => 'Both view_id and display_type are required.'];
    }

    $options = [];
    if (!empty($input['path'])) {
      $options['path'] = $input['path'];
    }
    if (!empty($input['title'])) {
      $options['title'] = $input['title'];
    }
    if (!empty($input['items_per_page'])) {
      $options['items_per_page'] = (int) $input['items_per_page'];
    }

    return $this->viewsService->addDisplay($viewId, $displayType, $options);
  }


}
