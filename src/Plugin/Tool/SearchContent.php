<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\ContentAnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for searching content.
 *
 * @Tool(
 *   id = "mcp_tools_search_content",
 *   label = @Translation("Search Content"),
 *   description = @Translation("Search for content by title text. Returns matching nodes with basic metadata."),
 *   category = "content",
 * )
 */
class SearchContent extends ToolPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The content analysis service.
   */
  protected ContentAnalysisService $contentAnalysis;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->contentAnalysis = $container->get('mcp_tools.content_analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $query = $input['query'] ?? '';
    $limit = min($input['limit'] ?? 20, 100);
    $type = $input['content_type'] ?? NULL;

    if (empty($query)) {
      return [
        'success' => FALSE,
        'error' => 'Search query is required.',
      ];
    }

    return [
      'success' => TRUE,
      'data' => $this->contentAnalysis->searchContent($query, $limit, $type),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'query' => [
        'type' => 'string',
        'label' => 'Search Query',
        'description' => 'Text to search for in content titles. Minimum 3 characters.',
        'required' => TRUE,
      ],
      'limit' => [
        'type' => 'integer',
        'label' => 'Limit',
        'description' => 'Maximum number of results to return. Max 100.',
        'required' => FALSE,
        'default' => 20,
      ],
      'content_type' => [
        'type' => 'string',
        'label' => 'Content Type',
        'description' => 'Filter by content type machine name. Leave empty for all types.',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'query' => [
        'type' => 'string',
        'label' => 'Search Query',
      ],
      'total' => [
        'type' => 'integer',
        'label' => 'Total Results',
      ],
      'results' => [
        'type' => 'list',
        'label' => 'Search Results',
      ],
    ];
  }

}
