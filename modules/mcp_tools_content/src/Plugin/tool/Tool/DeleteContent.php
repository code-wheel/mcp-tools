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
  id: 'mcp_delete_content',
  label: new TranslatableMarkup('Delete Content'),
  description: new TranslatableMarkup('Permanently delete a node.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'nid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The node ID to delete. WARNING: This is permanent and cannot be undone.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'nid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Deleted Node ID'),
      description: new TranslatableMarkup('The ID of the deleted node.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('The title of the deleted node.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Confirmation message.'),
    ),
  ],
)]
class DeleteContent extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'content';


  protected ContentService $contentService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->contentService = $container->get('mcp_tools_content.content');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $nid = $input['nid'] ?? 0;

    if (empty($nid)) {
      return ['success' => FALSE, 'error' => 'Node ID (nid) is required.'];
    }

    return $this->contentService->deleteContent((int) $nid);
  }


}
