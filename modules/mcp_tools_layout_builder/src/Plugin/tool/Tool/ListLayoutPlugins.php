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
  id: 'mcp_layout_list_plugins',
  label: new TranslatableMarkup('List Layout Plugins'),
  description: new TranslatableMarkup('List available layout plugins (one-column, two-column, etc.).'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'layouts' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Available Layouts'),
      description: new TranslatableMarkup(''),
    ),
    'count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Count'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class ListLayoutPlugins extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'layout_builder';


  protected LayoutBuilderService $layoutBuilderService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->layoutBuilderService = $container->get('mcp_tools_layout_builder.layout_builder');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    return $this->layoutBuilderService->listLayoutPlugins();
  }


}
