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
  id: 'mcp_layout_allow_custom',
  label: new TranslatableMarkup('Allow Custom Layouts'),
  description: new TranslatableMarkup('Toggle per-entity layout overrides for a content type.'),
  operation: ToolOperation::Read,
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
    'allow' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Allow Custom Layouts'),
      description: new TranslatableMarkup('True to enable per-entity overrides, false to disable. Defaults to true.'),
      required: FALSE,
      default_value: TRUE,
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
    'allow_custom_layouts' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Allow Custom Layouts'),
      description: new TranslatableMarkup('Current state of per-entity layout overrides.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success or error details.'),
    ),
  ],
)]
class AllowCustomLayouts extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'layout_builder';


  /**
   * The layout builder service.
   *
   * @var \Drupal\mcp_tools_layout_builder\Service\LayoutBuilderService
   */
  protected LayoutBuilderService $layoutBuilderService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->layoutBuilderService = $container->get('mcp_tools_layout_builder.layout_builder');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $entityType = $input['entity_type'] ?? 'node';
    $bundle = $input['bundle'] ?? '';
    $allow = $input['allow'] ?? TRUE;

    if (empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Bundle (content type) is required.'];
    }

    return $this->layoutBuilderService->allowCustomLayouts($entityType, $bundle, (bool) $allow);
  }

}
