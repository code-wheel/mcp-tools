<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_media\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_media\Service\MediaService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_create_media_type",
 *   label = @Translation("Create Media Type"),
 *   description = @Translation("Create a new media type with a specified source plugin."),
 *   category = "media",
 * )
 */
class CreateMediaType extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected MediaService $mediaService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->mediaService = $container->get('mcp_tools_media.media');
    return $instance;
  }

  public function execute(array $input = []): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';
    $sourcePlugin = $input['source_plugin'] ?? '';

    if (empty($id) || empty($label) || empty($sourcePlugin)) {
      return ['success' => FALSE, 'error' => 'id, label, and source_plugin are required.'];
    }

    return $this->mediaService->createMediaType($id, $label, $sourcePlugin);
  }

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Media Type ID', 'required' => TRUE],
      'label' => ['type' => 'string', 'label' => 'Label', 'required' => TRUE],
      'source_plugin' => ['type' => 'string', 'label' => 'Source Plugin', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Media Type ID'],
      'label' => ['type' => 'string', 'label' => 'Label'],
      'source_plugin' => ['type' => 'string', 'label' => 'Source Plugin'],
      'source_field' => ['type' => 'string', 'label' => 'Source Field'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
