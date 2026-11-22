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
  id: 'mcp_publish_content',
  label: new TranslatableMarkup('Publish/Unpublish Content'),
  description: new TranslatableMarkup('Publish or unpublish a content item.'),
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
    'language' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Language'),
      description: new TranslatableMarkup('A language code such as "fr", to publish or unpublish that language version only. Without it the source language is changed. Each language version is published on its own.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'nid' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The node ID that was modified.'),
    ),
    'status' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('New Status'),
      description: new TranslatableMarkup('New publish status: "published" or "unpublished".'),
    ),
    'language' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Language'),
      description: new TranslatableMarkup('The language version that was changed.'),
    ),
    'languages' => new OutputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Language Versions'),
      description: new TranslatableMarkup('Every language version of the node with its status now: "published" or "unpublished".'),
    ),
    'changed' => new OutputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Status Changed'),
      description: new TranslatableMarkup('True if status was actually changed, false if it was already in the requested state.'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable result message.'),
    ),
  ],
)]
class PublishContent extends McpToolsToolBase {

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
    $nid = $input['nid'] ?? 0;

    if (empty($nid)) {
      return ['success' => FALSE, 'error' => 'Node ID (nid) is required.'];
    }

    $language = isset($input['language']) && is_string($input['language']) && $input['language'] !== '' ? $input['language'] : NULL;
    return $this->contentService->setPublishStatus((int) $nid, (bool) ($input['publish'] ?? TRUE), $language);
  }

}
