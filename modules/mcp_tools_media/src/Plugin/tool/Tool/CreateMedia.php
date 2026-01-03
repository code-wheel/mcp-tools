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
  id: 'mcp_create_media',
  label: new TranslatableMarkup('Create Media'),
  description: new TranslatableMarkup('Create a new media entity.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Media Type (Bundle)'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Name'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'source_field_value' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Source Field Value'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'mid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Media ID'),
      description: new TranslatableMarkup(''),
    ),
    'uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('UUID'),
      description: new TranslatableMarkup(''),
    ),
    'name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Name'),
      description: new TranslatableMarkup(''),
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class CreateMedia extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'media';


  protected MediaService $mediaService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->mediaService = $container->get('mcp_tools_media.media');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
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


}
