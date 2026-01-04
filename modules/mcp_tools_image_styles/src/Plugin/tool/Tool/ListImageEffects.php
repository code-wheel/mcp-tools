<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_image_styles\Plugin\tool\Tool;

use Drupal\mcp_tools_image_styles\Service\ImageStyleService;
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
  id: 'mcp_image_styles_list_effects',
  label: new TranslatableMarkup('List Image Effects'),
  description: new TranslatableMarkup('List all available image effect plugins.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total effects'),
      description: new TranslatableMarkup('Number of available image effect plugins.'),
    ),
    'effects' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Available image effects'),
      description: new TranslatableMarkup('Array of effects with id and label. Use id with AddImageEffect (e.g., image_scale, image_crop).'),
    ),
  ],
)]
class ListImageEffects extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'image_styles';


  protected ImageStyleService $imageStyleService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->imageStyleService = $container->get('mcp_tools_image_styles.image_style_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return [
      'success' => TRUE,
      'data' => $this->imageStyleService->listImageEffects(),
    ];
  }

  

  

}
