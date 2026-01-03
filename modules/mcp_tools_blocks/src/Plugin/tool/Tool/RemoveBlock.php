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
  id: 'mcp_remove_block',
  label: new TranslatableMarkup('Remove Block'),
  description: new TranslatableMarkup('Remove a placed block from a theme.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'block_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Block ID'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'block_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Block ID'),
      description: new TranslatableMarkup(''),
    ),
    'plugin_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Plugin ID'),
      description: new TranslatableMarkup(''),
    ),
    'region' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Region'),
      description: new TranslatableMarkup(''),
    ),
    'theme' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
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

  protected const MCP_CATEGORY = 'blocks';


  protected BlockService $blockService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->blockService = $container->get('mcp_tools_blocks.block');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $blockId = $input['block_id'] ?? '';

    if (empty($blockId)) {
      return ['success' => FALSE, 'error' => 'block_id is required.'];
    }

    return $this->blockService->removeBlock($blockId);
  }


}
