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
  id: 'mcp_create_media_type',
  label: new TranslatableMarkup('Create Media Type'),
  description: new TranslatableMarkup('Create a new media type with a specified source plugin.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Media Type ID'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'source_plugin' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Source Plugin'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Media Type ID'),
      description: new TranslatableMarkup(''),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup(''),
    ),
    'source_plugin' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Source Plugin'),
      description: new TranslatableMarkup(''),
    ),
    'source_field' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Source Field'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class CreateMediaType extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'media';


  protected MediaService $mediaService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->mediaService = $container->get('mcp_tools_media.media');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';
    $sourcePlugin = $input['source_plugin'] ?? '';

    if (empty($id) || empty($label) || empty($sourcePlugin)) {
      return ['success' => FALSE, 'error' => 'id, label, and source_plugin are required.'];
    }

    return $this->mediaService->createMediaType($id, $label, $sourcePlugin);
  }


}
