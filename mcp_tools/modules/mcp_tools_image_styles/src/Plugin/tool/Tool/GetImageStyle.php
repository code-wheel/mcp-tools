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
  id: 'mcp_image_styles_get',
  label: new TranslatableMarkup('Get Image Style'),
  description: new TranslatableMarkup('Get details of a specific image style including all effects.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'style_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Style ID'),
      description: new TranslatableMarkup('The machine name of the image style.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Style ID'),
      description: new TranslatableMarkup(''),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Style label'),
      description: new TranslatableMarkup(''),
    ),
    'effects' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Image effects'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GetImageStyle extends McpToolsToolBase {

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
    $styleId = $input['style_id'] ?? '';
    if (empty($styleId)) {
      return [
        'success' => FALSE,
        'error' => 'style_id is required.',
      ];
    }

    $style = $this->imageStyleService->getImageStyle($styleId);
    if ($style === NULL) {
      return [
        'success' => FALSE,
        'error' => "Image style '$styleId' not found.",
        'code' => 'NOT_FOUND',
      ];
    }

    return [
      'success' => TRUE,
      'data' => $style,
    ];
  }

  

  

}
