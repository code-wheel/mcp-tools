<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_content\Plugin\tool\Tool;

use Drupal\mcp_tools_content\Service\ContentService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
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
  description: new TranslatableMarkup('Create new content (node) of a specified type. It is saved as an unpublished draft unless status is true. For many rows from a list or a file, or rows to import again later with changes: mcp_migration_import_json or mcp_migration_import_csv (MCP Tools - Migration), where a second run updates and makes no copies.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('Content type machine name (e.g., "article", "page"). Use mcp_tools_list_content_types to see available types.'),
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
      description: new TranslatableMarkup('Field values by field machine name, for example {"body": "The text"}. Without it only the title is set. A text field takes a string or {"value": "...", "format": "basic_html"}. Entity refs: {"target_id": 123}. Images: {"target_id": fid}.'),
      required: FALSE,
    ),
    'status' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Published'),
      description: new TranslatableMarkup('True to publish immediately. Without it, or with false, the item is saved as an unpublished draft.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'url' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('URL'),
      description: new TranslatableMarkup('The address of the new content on the site.'),
      required: FALSE,
    ),
    'nid' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The created node ID. Use this for updates, deletions, or references.'),
    ),
    'uuid' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('UUID'),
      description: new TranslatableMarkup('Universally unique identifier for the node.'),
    ),
    'title' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('The title of the created node.'),
    ),
    'status' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup('Whether the new node is live: "published" or "draft".'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable success or error message.'),
    ),
  ],
)]
class CreateContent extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'content';


  /**
   * The content service.
   *
   * @var \Drupal\mcp_tools_content\Service\ContentService
   */
  protected ContentService $contentService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->contentService = $container->get('mcp_tools_content.content');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
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
