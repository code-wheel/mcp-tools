<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\ContentAnalysisService;
use Drupal\mcp_tools\Service\RateLimiter;
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
  id: 'mcp_tools_search_content',
  label: new TranslatableMarkup('Search Content'),
  description: new TranslatableMarkup('Search for content by title text. Returns matching nodes with basic metadata.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'query' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Search Query'),
      description: new TranslatableMarkup('Text to search for in content titles. Minimum 3 characters.'),
      required: TRUE,
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum number of results to return. Max 100.'),
      required: FALSE,
      default_value: 20,
    ),
    'content_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('Filter by content type machine name. Leave empty for all types.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'query' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Search Query'),
      description: new TranslatableMarkup(''),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Results'),
      description: new TranslatableMarkup(''),
    ),
    'results' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Search Results'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class SearchContent extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'content';


  /**
   * The content analysis service.
   */
  protected ContentAnalysisService $contentAnalysis;

  /**
   * Read operation rate limiter.
   */
  protected RateLimiter $rateLimiter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->contentAnalysis = $container->get('mcp_tools.content_analysis');
    $instance->rateLimiter = $container->get('mcp_tools.rate_limiter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $query = $input['query'] ?? '';
    $limit = min($input['limit'] ?? 20, 100);
    $type = $input['content_type'] ?? NULL;

    if (empty($query)) {
      return [
        'success' => FALSE,
        'error' => 'Search query is required.',
      ];
    }

    $rateCheck = $this->rateLimiter->checkReadLimit('content_search');
    if (!$rateCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $rateCheck['error'],
        'code' => $rateCheck['code'] ?? 'RATE_LIMIT_EXCEEDED',
        'retry_after' => $rateCheck['retry_after'] ?? NULL,
      ];
    }

    $data = $this->contentAnalysis->searchContent($query, $limit, $type);
    if (isset($data['error'])) {
      return [
        'success' => FALSE,
        'error' => $data['error'],
      ];
    }

    return [
      'success' => TRUE,
      'data' => $data,
    ];
  }

  

  

}
