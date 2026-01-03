<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_blocks\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_blocks\Service\BlockService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_place_block",
 *   label = @Translation("Place Block"),
 *   description = @Translation("Place a block in a theme region."),
 *   category = "blocks",
 * )
 */
class PlaceBlock extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected BlockService $blockService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->blockService = $container->get('mcp_tools_blocks.block');
    return $instance;
  }

  public function execute(array $input = []): array {
    $pluginId = $input['plugin_id'] ?? '';
    $region = $input['region'] ?? '';

    if (empty($pluginId) || empty($region)) {
      return ['success' => FALSE, 'error' => 'Both plugin_id and region are required.'];
    }

    $options = [];

    if (isset($input['theme'])) {
      $options['theme'] = $input['theme'];
    }

    if (isset($input['id'])) {
      $options['id'] = $input['id'];
    }

    if (isset($input['label'])) {
      $options['label'] = $input['label'];
    }

    if (isset($input['label_display'])) {
      $options['label_display'] = $input['label_display'];
    }

    if (isset($input['weight'])) {
      $options['weight'] = (int) $input['weight'];
    }

    if (isset($input['visibility'])) {
      $options['visibility'] = $input['visibility'];
    }

    if (isset($input['settings'])) {
      $options['settings'] = $input['settings'];
    }

    return $this->blockService->placeBlock($pluginId, $region, $options);
  }

  public function getInputDefinition(): array {
    return [
      'plugin_id' => ['type' => 'string', 'label' => 'Block Plugin ID', 'required' => TRUE],
      'region' => ['type' => 'string', 'label' => 'Theme Region', 'required' => TRUE],
      'theme' => ['type' => 'string', 'label' => 'Theme', 'required' => FALSE],
      'id' => ['type' => 'string', 'label' => 'Custom Block ID', 'required' => FALSE],
      'label' => ['type' => 'string', 'label' => 'Block Label', 'required' => FALSE],
      'label_display' => ['type' => 'string', 'label' => 'Label Display (visible/hidden)', 'required' => FALSE],
      'weight' => ['type' => 'integer', 'label' => 'Weight', 'required' => FALSE],
      'visibility' => ['type' => 'object', 'label' => 'Visibility Conditions', 'required' => FALSE],
      'settings' => ['type' => 'object', 'label' => 'Block Settings', 'required' => FALSE],
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
