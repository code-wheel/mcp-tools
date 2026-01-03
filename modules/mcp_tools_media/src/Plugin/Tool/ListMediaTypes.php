<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_media\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_media\Service\MediaService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_list_media_types",
 *   label = @Translation("List Media Types"),
 *   description = @Translation("List all available media types with their source plugins."),
 *   category = "media",
 * )
 */
class ListMediaTypes extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected MediaService $mediaService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->mediaService = $container->get('mcp_tools_media.media');
    return $instance;
  }

  public function execute(array $input = []): array {
    return $this->mediaService->listMediaTypes();
  }

  public function getInputDefinition(): array {
    return [];
  }

  public function getOutputDefinition(): array {
    return [
      'types' => ['type' => 'array', 'label' => 'Media Types'],
      'count' => ['type' => 'integer', 'label' => 'Count'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
