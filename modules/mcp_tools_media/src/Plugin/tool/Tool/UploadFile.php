<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_media\Plugin\tool\Tool;

use Drupal\mcp_tools_media\Service\MediaService;
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
  id: 'mcp_upload_file',
  label: new TranslatableMarkup('Upload File'),
  description: new TranslatableMarkup('Upload a file from base64 encoded data.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'filename' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Filename'),
      description: new TranslatableMarkup('Target filename with extension (e.g., "photo.jpg", "document.pdf").'),
      required: TRUE,
    ),
    'data' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Base64 Data'),
      description: new TranslatableMarkup('Base64-encoded file content. Do NOT include data URI prefix.'),
      required: TRUE,
    ),
    'directory' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Directory'),
      description: new TranslatableMarkup('Drupal stream wrapper path (e.g., "public://uploads"). Defaults to public://mcp-uploads.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'fid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('File ID'),
      description: new TranslatableMarkup('File entity ID. Use as source_field_value in CreateMedia for image/file types.'),
    ),
    'uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('UUID'),
      description: new TranslatableMarkup('Universally unique identifier for the file entity.'),
    ),
    'filename' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Filename'),
      description: new TranslatableMarkup('Actual filename (may differ if sanitized or deduplicated).'),
    ),
    'uri' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('URI'),
      description: new TranslatableMarkup('Drupal file URI (e.g., public://mcp-uploads/photo.jpg).'),
    ),
    'url' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('URL'),
      description: new TranslatableMarkup('Public URL for the uploaded file.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success confirmation or error details.'),
    ),
  ],
)]
class UploadFile extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'media';


  protected MediaService $mediaService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->mediaService = $container->get('mcp_tools_media.media');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $filename = $input['filename'] ?? '';
    $data = $input['data'] ?? '';
    $directory = $input['directory'] ?? 'public://mcp-uploads';

    if (empty($filename) || empty($data)) {
      return ['success' => FALSE, 'error' => 'filename and data are required.'];
    }

    return $this->mediaService->uploadFile($filename, $data, $directory);
  }


}
