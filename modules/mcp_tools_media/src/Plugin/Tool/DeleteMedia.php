<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_media\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_media\Service\MediaService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_delete_media",
 *   label = @Translation("Delete Media"),
 *   description = @Translation("Permanently delete a media entity."),
 *   category = "media",
 * )
 */
class DeleteMedia extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected MediaService $mediaService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->mediaService = $container->get('mcp_tools_media.media');
    return $instance;
  }

  public function execute(array $input = []): array {
    $mid = $input['mid'] ?? 0;

    if (empty($mid)) {
      return ['success' => FALSE, 'error' => 'Media ID (mid) is required.'];
    }

    return $this->mediaService->deleteMedia((int) $mid);
  }

  public function getInputDefinition(): array {
    return [
      'mid' => ['type' => 'integer', 'label' => 'Media ID', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'mid' => ['type' => 'integer', 'label' => 'Deleted Media ID'],
      'name' => ['type' => 'string', 'label' => 'Name'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
