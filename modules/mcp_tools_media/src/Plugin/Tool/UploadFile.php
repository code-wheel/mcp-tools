<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_media\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_media\Service\MediaService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_upload_file",
 *   label = @Translation("Upload File"),
 *   description = @Translation("Upload a file from base64 encoded data."),
 *   category = "media",
 * )
 */
class UploadFile extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected MediaService $mediaService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->mediaService = $container->get('mcp_tools_media.media');
    return $instance;
  }

  public function execute(array $input = []): array {
    $filename = $input['filename'] ?? '';
    $data = $input['data'] ?? '';
    $directory = $input['directory'] ?? 'public://mcp-uploads';

    if (empty($filename) || empty($data)) {
      return ['success' => FALSE, 'error' => 'filename and data are required.'];
    }

    return $this->mediaService->uploadFile($filename, $data, $directory);
  }

  public function getInputDefinition(): array {
    return [
      'filename' => ['type' => 'string', 'label' => 'Filename', 'required' => TRUE],
      'data' => ['type' => 'string', 'label' => 'Base64 Data', 'required' => TRUE],
      'directory' => ['type' => 'string', 'label' => 'Directory', 'required' => FALSE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'fid' => ['type' => 'integer', 'label' => 'File ID'],
      'uuid' => ['type' => 'string', 'label' => 'UUID'],
      'filename' => ['type' => 'string', 'label' => 'Filename'],
      'uri' => ['type' => 'string', 'label' => 'URI'],
      'url' => ['type' => 'string', 'label' => 'URL'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
