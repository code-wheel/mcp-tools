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
  id: 'mcp_configure_block',
  label: new TranslatableMarkup('Configure Block'),
  description: new TranslatableMarkup('Update configuration of a placed block.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'block_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Block ID'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'region' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Move to Region'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'weight' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Weight'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Block Label'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'label_display' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label Display (visible/hidden)'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'status' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Enable/Disable Block'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'visibility' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Visibility Conditions'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'settings' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Block Settings'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'block_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Block ID'),
      description: new TranslatableMarkup(''),
    ),
    'updated' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Updated Fields'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class ConfigureBlock extends McpToolsToolBase {

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


}
