<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_search_api\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\mcp_tools_search_api\Service\SearchApiService;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Search content using Search API.
 */
#[Tool(
  id: 'mcp_search_api_search',
  label: new TranslatableMarkup('Search Content'),
  description: new TranslatableMarkup('Search content using a Search API index. Returns matching items with relevance scores. Use ListIndexes to find available indexes.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'index' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Index ID'),
      description: new TranslatableMarkup('The Search API index ID to search. Use mcp_search_api_list_indexes to see available indexes.'),
      required: TRUE,
    ),
    'keywords' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Keywords'),
      description: new TranslatableMarkup('Search keywords. Supports the search backend\'s query syntax (e.g., Solr, Elasticsearch, or database).'),
      required: TRUE,
    ),
    'filters' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Filters'),
      description: new TranslatableMarkup('Optional field filters as key-value pairs (e.g., {"type": "article", "status": true}). Field names must match indexed fields.'),
      required: FALSE,
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum results to return (default 25, max 100).'),
      required: FALSE,
    ),
    'offset' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Offset'),
      description: new TranslatableMarkup('Pagination offset (default 0). Use with limit to page through results.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'items' => new ContextDefinition(
      data_type: 'any',
      label: new TranslatableMarkup('Search Results'),
      description: new TranslatableMarkup('Array of matching items with entity info, relevance score, and excerpt.'),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Results'),
      description: new TranslatableMarkup('Total number of matching items (before pagination).'),
    ),
    'has_more' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Has More'),
      description: new TranslatableMarkup('Whether more results exist beyond the current page.'),
    ),
  ],
)]
class SearchContent extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'search_api';

  protected SearchApiService $searchApiService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->searchApiService = $container->get('mcp_tools_search_api.search_api_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $indexId = $input['index'] ?? '';
    $keywords = $input['keywords'] ?? '';

    if (empty($indexId)) {
      return ['success' => FALSE, 'error' => 'index is required. Use mcp_search_api_list_indexes to find available indexes.'];
    }

    if (empty($keywords)) {
      return ['success' => FALSE, 'error' => 'keywords is required.'];
    }

    $filters = $input['filters'] ?? [];
    $limit = (int) ($input['limit'] ?? 25);
    $offset = (int) ($input['offset'] ?? 0);

    return $this->searchApiService->search($indexId, $keywords, $filters, $limit, $offset);
  }

}
