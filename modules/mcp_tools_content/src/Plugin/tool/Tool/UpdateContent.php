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
  id: 'mcp_update_content',
  label: new TranslatableMarkup('Update Content'),
  description: new TranslatableMarkup('Update an existing node. Creates a new revision.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'nid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'updates' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Updates'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'nid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup(''),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup(''),
    ),
    'revision_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('New Revision ID'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class UpdateContent extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'content';


  protected ContentService $contentService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->contentService = $container->get('mcp_tools_content.content');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $nid = $input['nid'] ?? 0;
    $updates = $input['updates'] ?? [];

    if (empty($nid)) {
      return ['success' => FALSE, 'error' => 'Node ID (nid) is required.'];
    }
    if (empty($updates)) {
      return ['success' => FALSE, 'error' => 'At least one field to update is required.'];
    }

    return $this->contentService->updateContent((int) $nid, $updates);
  }


}
