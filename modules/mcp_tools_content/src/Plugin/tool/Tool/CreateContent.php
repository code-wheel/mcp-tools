<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_content\Plugin\tool\Tool;

use Drupal\mcp_tools_content\Service\ContentService;
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
  id: 'mcp_create_content',
  label: new TranslatableMarkup('Create Content'),
  description: new TranslatableMarkup('Create new content (node) of a specified type.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('Content type machine name (e.g., "article", "page"). Use ListContentTypes to see available types.'),
      required: TRUE,
    ),
    'title' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('The node title.'),
      required: TRUE,
    ),
    'fields' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Fields'),
      description: new TranslatableMarkup('Field values as key-value pairs. Keys are field machine names. Text: {"value": "...", "format": "basic_html"}. Entity refs: {"target_id": 123}. Images: {"target_id": fid}.'),
      required: FALSE,
    ),
    'status' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Published'),
      description: new TranslatableMarkup('True to publish immediately, false to save as draft. Defaults to true.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'nid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The created node ID. Use this for updates, deletions, or references.'),
    ),
    'uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('UUID'),
      description: new TranslatableMarkup('Universally unique identifier for the node.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('The title of the created node.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable success or error message.'),
    ),
  ],
)]
class CreateContent extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'content';


  protected ContentService $contentService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->contentService = $container->get('mcp_tools_content.content');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $type = $input['type'] ?? '';
    $title = $input['title'] ?? '';

    if (empty($type) || empty($title)) {
      return ['success' => FALSE, 'error' => 'Both type and title are required.'];
    }

    $options = [];
    if (isset($input['status'])) {
      $options['status'] = (bool) $input['status'];
    }

    return $this->contentService->createContent($type, $title, $input['fields'] ?? [], $options);
  }


}
