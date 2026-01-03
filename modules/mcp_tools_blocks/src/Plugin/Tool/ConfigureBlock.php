<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_blocks\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_blocks\Service\BlockService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_configure_block",
 *   label = @Translation("Configure Block"),
 *   description = @Translation("Update configuration of a placed block."),
 *   category = "blocks",
 * )
 */
class ConfigureBlock extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    $config = [];

    if (isset($input['region'])) {
      $config['region'] = $input['region'];
    }

    if (isset($input['weight'])) {
      $config['weight'] = (int) $input['weight'];
    }

    if (isset($input['label'])) {
      $config['label'] = $input['label'];
    }

    if (isset($input['label_display'])) {
      $config['label_display'] = $input['label_display'];
    }

    if (isset($input['status'])) {
      $config['status'] = (bool) $input['status'];
    }

    if (isset($input['visibility'])) {
      $config['visibility'] = $input['visibility'];
    }

    if (isset($input['settings'])) {
      $config['settings'] = $input['settings'];
    }

    if (empty($config)) {
      return ['success' => FALSE, 'error' => 'At least one configuration option must be provided.'];
    }

    return $this->blockService->configureBlock($blockId, $config);
  }

  public function getInputDefinition(): array {
    return [
      'block_id' => ['type' => 'string', 'label' => 'Block ID', 'required' => TRUE],
      'region' => ['type' => 'string', 'label' => 'Move to Region', 'required' => FALSE],
      'weight' => ['type' => 'integer', 'label' => 'Weight', 'required' => FALSE],
      'label' => ['type' => 'string', 'label' => 'Block Label', 'required' => FALSE],
      'label_display' => ['type' => 'string', 'label' => 'Label Display (visible/hidden)', 'required' => FALSE],
      'status' => ['type' => 'boolean', 'label' => 'Enable/Disable Block', 'required' => FALSE],
      'visibility' => ['type' => 'object', 'label' => 'Visibility Conditions', 'required' => FALSE],
      'settings' => ['type' => 'object', 'label' => 'Block Settings', 'required' => FALSE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'block_id' => ['type' => 'string', 'label' => 'Block ID'],
      'updated' => ['type' => 'array', 'label' => 'Updated Fields'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
