<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_media\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_media\Service\MediaService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_create_media",
 *   label = @Translation("Create Media"),
 *   description = @Translation("Create a new media entity."),
 *   category = "media",
 * )
 */
class CreateMedia extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected MediaService $mediaService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->mediaService = $container->get('mcp_tools_media.media');
    return $instance;
  }

  public function execute(array $input = []): array {
    $bundle = $input['bundle'] ?? '';
    $name = $input['name'] ?? '';
    $sourceFieldValue = $input['source_field_value'] ?? NULL;

    if (empty($bundle) || empty($name)) {
      return ['success' => FALSE, 'error' => 'bundle and name are required.'];
    }

    if ($sourceFieldValue === NULL) {
      return ['success' => FALSE, 'error' => 'source_field_value is required.'];
    }

    return $this->mediaService->createMedia($bundle, $name, $sourceFieldValue);
  }

  public function getInputDefinition(): array {
    return [
      'bundle' => ['type' => 'string', 'label' => 'Media Type (Bundle)', 'required' => TRUE],
      'name' => ['type' => 'string', 'label' => 'Name', 'required' => TRUE],
      'source_field_value' => ['type' => 'mixed', 'label' => 'Source Field Value', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'mid' => ['type' => 'integer', 'label' => 'Media ID'],
      'uuid' => ['type' => 'string', 'label' => 'UUID'],
      'name' => ['type' => 'string', 'label' => 'Name'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
