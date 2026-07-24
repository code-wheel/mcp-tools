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
  id: 'mcp_generate_alt_text',
  label: new TranslatableMarkup('Generate Alt Text'),
  description: new TranslatableMarkup("Generate alt text for an image media item using the connected MCP client's own model (sampling): the image is sent to the client, its model describes it, and the description is stored as the image alt text. Requires a client that supports MCP sampling."),
  operation: ToolOperation::Write,
  destructive: FALSE,
  input_definitions: [
    'mid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Media ID'),
      description: new TranslatableMarkup('The image media entity ID.'),
      required: TRUE,
    ),
    'overwrite' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Overwrite'),
      description: new TranslatableMarkup('Replace existing alt text. Without this, media that already has alt text is left untouched.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'mid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Media ID'),
      description: new TranslatableMarkup('The updated media entity ID.'),
    ),
    'alt' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Alt Text'),
      description: new TranslatableMarkup('The generated alt text that was stored.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success confirmation.'),
    ),
  ],
)]
class GenerateAltText extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'media';

  protected const MCP_WRITE_KIND = 'content';

  /**
   * The media service.
   *
   * @var \Drupal\mcp_tools_media\Service\MediaService
   */
  protected MediaService $mediaService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->mediaService = $container->get('mcp_tools_media.media');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $mid = (int) ($input['mid'] ?? 0);
    if ($mid <= 0) {
      return ['success' => FALSE, 'error' => 'Media ID (mid) is required.'];
    }
    return $this->mediaService->generateAltText($mid, (bool) ($input['overwrite'] ?? FALSE));
  }

}
