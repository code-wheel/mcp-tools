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
  id: 'mcp_image_styles_list',
  label: new TranslatableMarkup('List Image Styles'),
  description: new TranslatableMarkup('List all image styles with their effects.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total image styles'),
      description: new TranslatableMarkup(''),
    ),
    'styles' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Image styles with effects'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class ListImageStyles extends McpToolsToolBase {

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
      'data' => $this->imageStyleService->listImageStyles(),
    ];
  }

  

  

}
