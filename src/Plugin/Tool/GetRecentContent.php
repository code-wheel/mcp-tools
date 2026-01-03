<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\ContentAnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting recent content.
 *
 * @Tool(
 *   id = "mcp_tools_get_recent_content",
 *   label = @Translation("Get Recent Content"),
 *   description = @Translation("Get recently created or modified content from the Drupal site."),
 *   category = "content",
 * )
 */
class GetRecentContent extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'limit' => [
        'type' => 'integer',
        'label' => 'Limit',
        'description' => 'Maximum number of items to return. Max 100.',
        'required' => FALSE,
        'default' => 20,
      ],
      'content_type' => [
        'type' => 'string',
        'label' => 'Content Type',
        'description' => 'Filter by content type machine name (e.g., "article", "page"). Leave empty for all types.',
        'required' => FALSE,
      ],
      'sort' => [
        'type' => 'string',
        'label' => 'Sort By',
        'description' => 'Sort by "created" or "changed" date.',
        'required' => FALSE,
        'default' => 'changed',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total' => [
        'type' => 'integer',
        'label' => 'Total Items Returned',
      ],
      'sorted_by' => [
        'type' => 'string',
        'label' => 'Sorted By',
      ],
      'content' => [
        'type' => 'list',
        'label' => 'Content Items',
      ],
    ];
  }

}
