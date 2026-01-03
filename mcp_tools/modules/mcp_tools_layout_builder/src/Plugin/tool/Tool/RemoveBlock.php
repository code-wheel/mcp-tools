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
  id: 'mcp_layout_remove_block',
  label: new TranslatableMarkup('Remove Block'),
  description: new TranslatableMarkup('Remove a block from the layout by its UUID.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
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
    'block_uuid' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Block Component UUID'),
      description: new TranslatableMarkup(''),
      required: TRUE,
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
    'removed_block_uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Removed Block UUID'),
      description: new TranslatableMarkup(''),
    ),
    'section_delta' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Section Delta'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class RemoveBlock extends McpToolsToolBase {

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
    $blockUuid = $input['block_uuid'] ?? '';

    if (empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Bundle (content type) is required.'];
    }

    if (empty($blockUuid)) {
      return ['success' => FALSE, 'error' => 'Block UUID is required. Use mcp_layout_get to see block UUIDs.'];
    }

    return $this->layoutBuilderService->removeBlock($entityType, $bundle, $blockUuid);
  }


}
