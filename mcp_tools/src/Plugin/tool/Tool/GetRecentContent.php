<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\ContentAnalysisService;
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
  id: 'mcp_tools_get_recent_content',
  label: new TranslatableMarkup('Get Recent Content'),
  description: new TranslatableMarkup('Get recently created or modified content from the Drupal site.'),
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
    'sort' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Sort By'),
      description: new TranslatableMarkup('Sort by "created" or "changed" date.'),
      required: FALSE,
      default_value: 'changed',
    ),
  ],
  output_definitions: [
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Items Returned'),
      description: new TranslatableMarkup(''),
    ),
    'sorted_by' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Sorted By'),
      description: new TranslatableMarkup(''),
    ),
    'content' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Content Items'),
      description: new TranslatableMarkup(''),
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
    $type = $input['content_type'] ?? NULL;
    $sort = in_array($input['sort'] ?? 'changed', ['created', 'changed'])
      ? $input['sort']
      : 'changed';

    return [
      'success' => TRUE,
      'data' => $this->contentAnalysis->getRecentContent($limit, $type, $sort),
    ];
  }

  

  

}
