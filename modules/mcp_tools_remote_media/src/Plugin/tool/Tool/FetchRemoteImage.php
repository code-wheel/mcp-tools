<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote_media\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\mcp_tools_remote_media\Service\RemoteImageService;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool plugin: fetch a remote image and create a managed Drupal media entity.
 */
#[Tool(
  id: 'mcp_fetch_remote_image',
  label: new TranslatableMarkup('Fetch Remote Image'),
  description: new TranslatableMarkup('Download an image from a remote URL and create a managed Drupal media entity. Supports JPEG, PNG, GIF, WebP.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'url' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Image URL'),
      description: new TranslatableMarkup('Full URL of the remote image to fetch (http/https only). Example: https://example.com/photo.jpg'),
      required: TRUE,
    ),
    'name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Media Name'),
      description: new TranslatableMarkup('Human-readable name for the media entity shown in the media library.'),
      required: TRUE,
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Media Type'),
      description: new TranslatableMarkup('Media type machine name (e.g. "image"). Defaults to "image". Use mcp_list_media_types to see available types.'),
      required: FALSE,
    ),
    'directory' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Directory'),
      description: new TranslatableMarkup('Drupal stream wrapper path where the file will be saved. Defaults to public://mcp-uploads.'),
      required: FALSE,
    ),
    'create_media' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Create Media Entity'),
      description: new TranslatableMarkup('If true (default), creates a media entity after saving the file. If false, only saves the managed file.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'fid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('File ID'),
      description: new TranslatableMarkup('Managed file entity ID.'),
    ),
    'mid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Media ID'),
      description: new TranslatableMarkup('Media entity ID (only when create_media is true).'),
    ),
    'filename' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Filename'),
      description: new TranslatableMarkup('Sanitized filename as stored on disk.'),
    ),
    'uri' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('URI'),
      description: new TranslatableMarkup('Drupal file URI (e.g. public://mcp-uploads/photo.jpg).'),
    ),
    'url' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Public URL'),
      description: new TranslatableMarkup('Publicly accessible URL of the saved file.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success confirmation or error details.'),
    ),
  ],
)]
class FetchRemoteImage extends McpToolsToolBase {

  /**
   * The MCP tool category.
   */
  protected const MCP_CATEGORY = 'remote media';

  /**
   * The remote image service.
   *
   * @var \Drupal\mcp_tools_remote_media\Service\RemoteImageService
   */
  protected RemoteImageService $remoteImageService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->remoteImageService = $container->get('mcp_tools_remote_media.remote_image');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $url = $input['url'] ?? '';
    $name = $input['name'] ?? '';
    $bundle = $input['bundle'] ?? 'image';
    $directory = $input['directory'] ?? 'public://mcp-uploads';
    $createMedia = isset($input['create_media']) ? (bool) $input['create_media'] : TRUE;

    if (empty($url)) {
      return ['success' => FALSE, 'error' => 'url is required.'];
    }

    if (empty($name)) {
      return ['success' => FALSE, 'error' => 'name is required.'];
    }

    return $this->remoteImageService->fetchRemoteImage($url, $name, $directory, $bundle, $createMedia);
  }

}
