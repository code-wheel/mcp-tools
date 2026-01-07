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
  id: 'mcp_layout_get',
  label: new TranslatableMarkup('Get Layout'),
  description: new TranslatableMarkup('Get the default layout sections for a content type.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('Entity type to get layout for. Defaults to "node".'),
      required: FALSE,
      default_value: 'node',
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle/Content Type'),
      description: new TranslatableMarkup('Machine name of the content type (e.g., "article", "page").'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type queried.'),
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('The bundle queried.'),
    ),
    'layout_builder_enabled' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Layout Builder Enabled'),
      description: new TranslatableMarkup('Whether Layout Builder is enabled for this bundle.'),
    ),
    'allow_custom_layouts' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Allow Custom Layouts'),
      description: new TranslatableMarkup('Whether per-entity layout overrides are allowed.'),
    ),
    'sections' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Layout Sections'),
      description: new TranslatableMarkup('Array of sections with layout_id, settings, and components.'),
    ),
    'section_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Section Count'),
      description: new TranslatableMarkup('Number of sections in the layout.'),
    ),
  ],
)]
class GetLayout extends McpToolsToolBase {

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

    if (empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Bundle (content type) is required.'];
    }

    return $this->layoutBuilderService->getLayout($entityType, $bundle);
  }


}
