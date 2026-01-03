<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_analysis\Service\AnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for finding duplicate content.
 *
 * @Tool(
 *   id = "mcp_analysis_duplicates",
 *   label = @Translation("Find Duplicate Content"),
 *   description = @Translation("Find similar content based on field values. Useful for identifying redundant pages."),
 *   category = "analysis",
 * )
 */
class FindDuplicateContent extends ToolPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The analysis service.
   */
  protected AnalysisService $analysisService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->analysisService = $container->get('mcp_tools_analysis.analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $contentType = $input['content_type'] ?? '';
    $field = $input['field'] ?? 'title';
    $threshold = isset($input['threshold']) ? (float) $input['threshold'] : 0.8;

    if (empty($contentType)) {
      return ['success' => FALSE, 'error' => 'content_type is required.'];
    }

    if ($threshold < 0.1 || $threshold > 1.0) {
      return ['success' => FALSE, 'error' => 'threshold must be between 0.1 and 1.0.'];
    }

    return $this->analysisService->findDuplicateContent($contentType, $field, $threshold);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'content_type' => [
        'type' => 'string',
        'label' => 'Content Type',
        'description' => 'Machine name of the content type to search',
        'required' => TRUE,
      ],
      'field' => [
        'type' => 'string',
        'label' => 'Field',
        'description' => 'Field to compare for similarity (default: "title")',
        'required' => FALSE,
      ],
      'threshold' => [
        'type' => 'number',
        'label' => 'Similarity Threshold',
        'description' => 'Similarity threshold 0.1-1.0 (default: 0.8 = 80% similar)',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'content_type' => [
        'type' => 'string',
        'label' => 'Content Type',
      ],
      'field_compared' => [
        'type' => 'string',
        'label' => 'Field Compared',
      ],
      'threshold' => [
        'type' => 'number',
        'label' => 'Threshold Used',
      ],
      'items_analyzed' => [
        'type' => 'integer',
        'label' => 'Items Analyzed',
      ],
      'duplicates' => [
        'type' => 'array',
        'label' => 'Potential Duplicates',
        'description' => 'Pairs of similar content with similarity percentage',
      ],
      'duplicate_count' => [
        'type' => 'integer',
        'label' => 'Duplicate Count',
      ],
      'suggestions' => [
        'type' => 'array',
        'label' => 'Suggestions',
      ],
    ];
  }

}
