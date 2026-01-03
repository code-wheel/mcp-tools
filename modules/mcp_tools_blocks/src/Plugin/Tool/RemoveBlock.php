<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_blocks\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_blocks\Service\BlockService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_remove_block",
 *   label = @Translation("Remove Block"),
 *   description = @Translation("Remove a placed block from a theme."),
 *   category = "blocks",
 * )
 */
class RemoveBlock extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected BlockService $blockService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->blockService = $container->get('mcp_tools_blocks.block');
    return $instance;
  }

  public function execute(array $input = []): array {
    $blockId = $input['block_id'] ?? '';

    if (empty($blockId)) {
      return ['success' => FALSE, 'error' => 'block_id is required.'];
    }

    return $this->blockService->removeBlock($blockId);
  }

  public function getInputDefinition(): array {
    return [
      'block_id' => ['type' => 'string', 'label' => 'Block ID', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'block_id' => ['type' => 'string', 'label' => 'Block ID'],
      'plugin_id' => ['type' => 'string', 'label' => 'Plugin ID'],
      'region' => ['type' => 'string', 'label' => 'Region'],
      'theme' => ['type' => 'string', 'label' => 'Theme'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
