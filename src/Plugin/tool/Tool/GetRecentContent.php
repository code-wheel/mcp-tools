<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\ContentAnalysisService;
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
  id: 'mcp_tools_get_recent_content',
  label: new TranslatableMarkup('Get Recent Content'),
  description: new TranslatableMarkup('List content, newest first, optionally of one type and only published or only unpublished. "matching" says how many items match in all, also beyond the limit: use it to count content.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum number of items to return. Max 100.'),
      required: FALSE,
      default_value: 20,
    ),
    'content_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('Filter by content type machine name (e.g., "article", "page"). Leave empty for all types.'),
      required: FALSE,
    ),
    'status' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup('"published" or "unpublished". Leave empty for both.'),
      required: FALSE,
    ),
    'sort' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Sort By'),
      description: new TranslatableMarkup('Sort by "created" or "changed" date.'),
      required: FALSE,
      default_value: 'changed',
    ),
  ],
  output_definitions: [
    'total' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Items Returned'),
      description: new TranslatableMarkup('Number of content items in this answer, at most the limit. Not a count of the site: see matching.'),
    ),
    'matching' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Matching Items'),
      description: new TranslatableMarkup('How many items match the type and status in all, also beyond the limit.'),
    ),
    'sorted_by' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Sorted By'),
      description: new TranslatableMarkup('Field used for sorting: "created" or "changed".'),
    ),
    'content' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Content Items'),
      description: new TranslatableMarkup('Array of nodes with nid, title, type, status, author, created, and changed timestamps. Use nid to update/delete content.'),
    ),
  ],
)]
class GetRecentContent extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'content';


  /**
   * The content analysis service.
   */
  protected ContentAnalysisService $contentAnalysis;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->contentAnalysis = $container->get('mcp_tools.content_analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $limit = min($input['limit'] ?? 20, 100);
    $type = ($input['content_type'] ?? NULL) ?: NULL;
    // Read once to avoid returning NULL when the key is absent.
    $sortInput = $input['sort'] ?? 'changed';
    $sort = in_array($sortInput, ['created', 'changed']) ? $sortInput : 'changed';
    // "1" and "true" are what a model sends for published as well.
    $status = strtolower(trim((string) ($input['status'] ?? '')));
    $published = match ($status) {
      'published', '1', 'true', 'yes' => TRUE,
      'unpublished', '0', 'false', 'no' => FALSE,
      '' => NULL,
      default => 'wrong',
    };
    if ($published === 'wrong') {
      return ['success' => FALSE, 'error' => 'status is "published" or "unpublished", or empty for both.'];
    }

    return [
      'success' => TRUE,
      'data' => $this->contentAnalysis->getRecentContent($limit, $type, $sort, $published),
    ];
  }

}
