<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_blocks\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_blocks\Service\BlockService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_list_available_blocks",
 *   label = @Translation("List Available Blocks"),
 *   description = @Translation("List all available block plugins that can be placed."),
 *   category = "blocks",
 * )
 */
class ListAvailableBlocks extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected BlockService $blockService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->blockService = $container->get('mcp_tools_blocks.block');
    return $instance;
  }

  public function execute(array $input = []): array {
    return $this->blockService->listAvailableBlocks();
  }

  public function getInputDefinition(): array {
    return [];
  }

  public function getOutputDefinition(): array {
    return [
      'blocks' => ['type' => 'array', 'label' => 'Available Blocks'],
      'count' => ['type' => 'integer', 'label' => 'Block Count'],
    ];
  }

}
