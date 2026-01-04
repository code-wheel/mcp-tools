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
  id: 'mcp_layout_add_block',
  label: new TranslatableMarkup('Add Block'),
  description: new TranslatableMarkup('Add a block to a section in the layout.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('Entity type. Defaults to "node".'),
      required: FALSE,
      default_value: 'node',
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle/Content Type'),
      description: new TranslatableMarkup('Machine name of the content type (e.g., "article").'),
      required: TRUE,
    ),
    'section_delta' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Section Delta'),
      description: new TranslatableMarkup('Section index to add block to (0-based). Defaults to 0.'),
      required: FALSE,
      default_value: 0,
    ),
    'region' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Region'),
      description: new TranslatableMarkup('Region within section (e.g., "content", "first", "second"). Depends on layout.'),
      required: TRUE,
    ),
    'block_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Block Plugin ID'),
      description: new TranslatableMarkup('Block plugin ID (e.g., "system_branding_block", "field_block:node:article:body").'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type configured.'),
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('The bundle configured.'),
    ),
    'section_delta' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Section Delta'),
      description: new TranslatableMarkup('Section where block was added.'),
    ),
    'region' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Region'),
      description: new TranslatableMarkup('Region where block was placed.'),
    ),
    'block_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Block ID'),
      description: new TranslatableMarkup('Block plugin that was added.'),
    ),
    'component_uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Component UUID'),
      description: new TranslatableMarkup('UUID of the block component. Use with RemoveBlock to remove.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success or error details.'),
    ),
  ],
)]
class AddBlock extends McpToolsToolBase {

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
    $sectionDelta = $input['section_delta'] ?? 0;
    $region = $input['region'] ?? '';
    $blockId = $input['block_id'] ?? '';

    if (empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Bundle (content type) is required.'];
    }

    if (empty($region)) {
      return ['success' => FALSE, 'error' => 'Region is required.'];
    }

    if (empty($blockId)) {
      return ['success' => FALSE, 'error' => 'Block ID is required.'];
    }

    return $this->layoutBuilderService->addBlock($entityType, $bundle, (int) $sectionDelta, $region, $blockId);
  }


}
