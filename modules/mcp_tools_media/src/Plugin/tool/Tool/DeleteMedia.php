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
  id: 'mcp_delete_media',
  label: new TranslatableMarkup('Delete Media'),
  description: new TranslatableMarkup('Permanently delete a media entity.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'mid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Media ID'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'mid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Deleted Media ID'),
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
class DeleteMedia extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'media';


  protected MediaService $mediaService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->mediaService = $container->get('mcp_tools_media.media');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $mid = $input['mid'] ?? 0;

    if (empty($mid)) {
      return ['success' => FALSE, 'error' => 'Media ID (mid) is required.'];
    }

    return $this->mediaService->deleteMedia((int) $mid);
  }


}
