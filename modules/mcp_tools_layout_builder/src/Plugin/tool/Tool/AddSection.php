<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_layout_builder\Plugin\tool\Tool;

use Drupal\mcp_tools_layout_builder\Service\LayoutBuilderService;
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
  id: 'mcp_layout_add_section',
  label: new TranslatableMarkup('Add Section'),
  description: new TranslatableMarkup('Add a layout section to the default layout.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup(''),
      required: FALSE,
      default_value: 'node',
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle/Content Type'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'layout_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Layout Plugin ID'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'delta' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Position (Delta)'),
      description: new TranslatableMarkup(''),
      required: FALSE,
      default_value: 0,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup(''),
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup(''),
    ),
    'layout_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Layout ID'),
      description: new TranslatableMarkup(''),
    ),
    'delta' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Section Delta'),
      description: new TranslatableMarkup(''),
    ),
    'section_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Sections'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class AddSection extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'layout_builder';


  protected LayoutBuilderService $layoutBuilderService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->layoutBuilderService = $container->get('mcp_tools_layout_builder.layout_builder');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $entityType = $input['entity_type'] ?? 'node';
    $bundle = $input['bundle'] ?? '';
    $layoutId = $input['layout_id'] ?? '';
    $delta = $input['delta'] ?? 0;

    if (empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Bundle (content type) is required.'];
    }

    if (empty($layoutId)) {
      return ['success' => FALSE, 'error' => 'Layout ID is required. Use mcp_layout_list_plugins to see available layouts.'];
    }

    return $this->layoutBuilderService->addSection($entityType, $bundle, $layoutId, (int) $delta);
  }


}
