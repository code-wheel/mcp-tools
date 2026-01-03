<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_image_styles\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_image_styles\Service\ImageStyleService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing available image effects.
 *
 * @Tool(
 *   id = "mcp_image_styles_list_effects",
 *   label = @Translation("List Image Effects"),
 *   description = @Translation("List all available image effect plugins."),
 *   category = "image_styles",
 * )
 */
class ListImageEffects extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return [
      'success' => TRUE,
      'data' => $this->imageStyleService->listImageEffects(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total' => [
        'type' => 'integer',
        'label' => 'Total effects',
      ],
      'effects' => [
        'type' => 'list',
        'label' => 'Available image effects',
      ],
    ];
  }

}
