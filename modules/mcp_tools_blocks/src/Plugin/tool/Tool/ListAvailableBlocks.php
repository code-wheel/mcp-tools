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
  id: 'mcp_list_available_blocks',
  label: new TranslatableMarkup('List Available Blocks'),
  description: new TranslatableMarkup('List all available block plugins that can be placed.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'blocks' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Available Blocks'),
      description: new TranslatableMarkup(''),
    ),
    'count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Block Count'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class ListAvailableBlocks extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'blocks';


  protected BlockService $blockService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->blockService = $container->get('mcp_tools_blocks.block');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    return $this->blockService->listAvailableBlocks();
  }


}
