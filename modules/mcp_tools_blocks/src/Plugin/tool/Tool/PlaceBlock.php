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
  id: 'mcp_place_block',
  label: new TranslatableMarkup('Place Block'),
  description: new TranslatableMarkup('Place a block in a theme region.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'plugin_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Block Plugin ID'),
      description: new TranslatableMarkup('Block plugin ID from ListAvailableBlocks (e.g., "system_branding_block", "views_block:frontpage-block_1").'),
      required: TRUE,
    ),
    'region' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme Region'),
      description: new TranslatableMarkup('Region machine name from ListRegions (e.g., "sidebar_first", "content", "footer").'),
      required: TRUE,
    ),
    'theme' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
      description: new TranslatableMarkup('Theme machine name. Defaults to active theme if omitted.'),
      required: FALSE,
    ),
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Custom Block ID'),
      description: new TranslatableMarkup('Custom machine name for this placement. Auto-generated if omitted.'),
      required: FALSE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Block Label'),
      description: new TranslatableMarkup('Block title shown to users. Uses plugin default if omitted.'),
      required: FALSE,
    ),
    'label_display' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label Display (visible/hidden)'),
      description: new TranslatableMarkup('Show or hide block title: "visible" or "hidden". Defaults to visible.'),
      required: FALSE,
    ),
    'weight' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Weight'),
      description: new TranslatableMarkup('Sort order within region. Lower = higher position. Defaults to 0.'),
      required: FALSE,
    ),
    'visibility' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Visibility Conditions'),
      description: new TranslatableMarkup('Visibility rules: {"request_path": {"pages": "/about\\n/contact"}} or {"user_role": {"roles": {"authenticated": "authenticated"}}}.'),
      required: FALSE,
    ),
    'settings' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Block Settings'),
      description: new TranslatableMarkup('Plugin-specific settings. Varies by block type.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'block_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Block ID'),
      description: new TranslatableMarkup('Config entity ID of the placed block. Use with ConfigureBlock or RemoveBlock.'),
    ),
    'plugin_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Plugin ID'),
      description: new TranslatableMarkup('The block plugin that was placed.'),
    ),
    'region' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Region'),
      description: new TranslatableMarkup('Region where block was placed.'),
    ),
    'theme' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
      description: new TranslatableMarkup('Theme containing this block placement.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success confirmation or error details.'),
    ),
  ],
)]
class PlaceBlock extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'blocks';


  /**
   * The block service.
   *
   * @var \Drupal\mcp_tools_blocks\Service\BlockService
   */
  protected BlockService $blockService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->blockService = $container->get('mcp_tools_blocks.block');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
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

}
