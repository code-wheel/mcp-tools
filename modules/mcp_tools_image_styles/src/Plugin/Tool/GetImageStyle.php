<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_image_styles\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_image_styles\Service\ImageStyleService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting a specific image style.
 *
 * @Tool(
 *   id = "mcp_image_styles_get",
 *   label = @Translation("Get Image Style"),
 *   description = @Translation("Get details of a specific image style including all effects."),
 *   category = "image_styles",
 * )
 */
class GetImageStyle extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ImageStyleService $imageStyleService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->imageStyleService = $container->get('mcp_tools_image_styles.image_style_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
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

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'style_id' => [
        'type' => 'string',
        'label' => 'Style ID',
        'description' => 'The machine name of the image style.',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'id' => [
        'type' => 'string',
        'label' => 'Style ID',
      ],
      'label' => [
        'type' => 'string',
        'label' => 'Style label',
      ],
      'effects' => [
        'type' => 'list',
        'label' => 'Image effects',
      ],
    ];
  }

}
