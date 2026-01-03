<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_blocks\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_blocks\Service\BlockService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_list_regions",
 *   label = @Translation("List Regions"),
 *   description = @Translation("List available regions for a theme and blocks placed in them."),
 *   category = "blocks",
 * )
 */
class ListRegions extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected BlockService $blockService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->blockService = $container->get('mcp_tools_blocks.block');
    return $instance;
  }

  public function execute(array $input = []): array {
    $theme = $input['theme'] ?? NULL;
    return $this->blockService->listRegions($theme);
  }

  public function getInputDefinition(): array {
    return [
      'theme' => ['type' => 'string', 'label' => 'Theme', 'required' => FALSE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'theme' => ['type' => 'string', 'label' => 'Theme'],
      'regions' => ['type' => 'array', 'label' => 'Available Regions'],
      'count' => ['type' => 'integer', 'label' => 'Region Count'],
    ];
  }

}
