<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_blocks\Plugin\tool\Tool;

use Drupal\mcp_tools_blocks\Service\BlockService;
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
  id: 'mcp_list_regions',
  label: new TranslatableMarkup('List Regions'),
  description: new TranslatableMarkup('List available regions for a theme and blocks placed in them.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'theme' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
      description: new TranslatableMarkup('Theme machine name (e.g., "olivero"). Defaults to active theme if omitted.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'theme' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
      description: new TranslatableMarkup('Theme machine name queried.'),
    ),
    'regions' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Available Regions'),
      description: new TranslatableMarkup('Array of regions with id, label, and placed blocks. Use id as region in PlaceBlock.'),
    ),
    'count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Region Count'),
      description: new TranslatableMarkup('Number of regions in this theme.'),
    ),
  ],
)]
class ListRegions extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'blocks';


  protected BlockService $blockService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->blockService = $container->get('mcp_tools_blocks.block');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $theme = $input['theme'] ?? NULL;
    return $this->blockService->listRegions($theme);
  }


}
