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
  id: 'mcp_publish_content',
  label: new TranslatableMarkup('Publish/Unpublish Content'),
  description: new TranslatableMarkup('Change publish status of content.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'nid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The node ID to publish or unpublish.'),
      required: TRUE,
    ),
    'publish' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Publish'),
      description: new TranslatableMarkup('True to publish, false to unpublish. Defaults to true.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'nid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The node ID that was modified.'),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('New Status'),
      description: new TranslatableMarkup('New publish status: "published" or "unpublished".'),
    ),
    'changed' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Status Changed'),
      description: new TranslatableMarkup('True if status was actually changed, false if it was already in the requested state.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable result message.'),
    ),
  ],
)]
class PublishContent extends McpToolsToolBase {

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

    return $this->contentService->setPublishStatus((int) $nid, (bool) ($input['publish'] ?? TRUE));
  }


}
